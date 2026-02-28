<?php
include('../database/config.php');
require_once('../config/api_key_helper.php');
define("TITLE", "Blogs");
include_once('../layouts/header.php');

// Get API key securely
$apiKey = getGeminiApiKey();

$today = date('Y-m-d');
$dayOfWeek = date('w'); // 0 = Sunday, 1 = Monday, etc.

// Check if it's Monday (1) - delete all blogs and start fresh
if ($dayOfWeek == 1) {
    $deleteAllStmt = $con->prepare("DELETE FROM dental_blogs");
    $deleteAllStmt->execute();
    $deleteAllStmt->close();
}

// Check total blog count - limit to 5 cards maximum
$countStmt = $con->prepare("SELECT COUNT(*) AS total FROM dental_blogs");
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalBlogs = $countResult->fetch_assoc()['total'];
$countStmt->close();

// Use prepared statement to prevent SQL injection
$checkStmt = $con->prepare("SELECT * FROM dental_blogs WHERE DATE(published_at) = ?");
$checkStmt->bind_param("s", $today);
$checkStmt->execute();
$result = $checkStmt->get_result();
$checkStmt->close();

// Only generate if no blog for today AND total blogs is less than 5
if (mysqli_num_rows($result) == 0 && $totalBlogs < 5) {
    // Only proceed if API key is available
    if (!$apiKey) {
        error_log("Blog generation failed: API key not found");
        // Use fallback content if API key is missing
        $title = "Dental Health Tip of the Day";
        $content = "Keep your smile healthy by brushing twice a day and visiting your dentist regularly!";
    } else {
        // Improved prompt for better formatted response
        $prompt = "Generate a short, friendly, and informative blog post for a modern dental clinic website.
The blog should be about dental care, oral hygiene, or smile tips.
Requirements:
- A catchy, engaging title
- Around 150-200 words of content
- Write in a friendly, professional tone
- Include practical tips or advice

Format your response EXACTLY like this:
Title: [Your title here]
Content: [Your content here]

Make sure the Title and Content are on separate lines and clearly labeled.";

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . urlencode($apiKey);
        
        $data = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $title = "Dental Health Tip of the Day";
        $content = "Keep your smile healthy by brushing twice a day and visiting your dentist regularly!";

        if ($response === false) {
            error_log("Blog API cURL error: " . $curlError);
        } elseif ($httpCode !== 200) {
            error_log("Blog API HTTP error: " . $httpCode);
        } else {
            $responseData = json_decode($response, true);
            $generatedText = '';

            // Check for API errors
            if (isset($responseData['error'])) {
                error_log("Blog API Error: " . $responseData['error']['message']);
            } elseif (isset($responseData['candidates'][0]['content']['parts'])) {
                // Collect text parts
                foreach ($responseData['candidates'][0]['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $generatedText .= $part['text'] . "\n";
                    }
                }

                // Parse the response
                if (preg_match('/Title:\s*(.*?)\nContent:\s*(.*)/s', $generatedText, $matches)) {
                    $title = trim($matches[1]);
                    $content = trim($matches[2]);
                } elseif (preg_match('/Title:\s*(.*?)$/m', $generatedText, $titleMatch)) {
                    // Try alternative parsing if format is slightly different
                    $title = trim($titleMatch[1]);
                    $content = preg_replace('/Title:.*?\n/', '', $generatedText);
                    $content = preg_replace('/Content:\s*/', '', $content, 1);
                    $content = trim($content);
                } else {
                    // Fallback: use the generated text as content
                    $content = trim($generatedText) ?: $content;
                }
            }
        }
    }

    // Generate new blog ID
    $idStmt = $con->prepare("SELECT blog_id FROM dental_blogs ORDER BY blog_id DESC LIMIT 1");
    $idStmt->execute();
    $idResult = $idStmt->get_result();
    
    if ($idResult->num_rows > 0) {
        $lastId = $idResult->fetch_assoc()['blog_id'];
        $num = (int)substr($lastId, 1) + 1; // remove 'B' and increment
        $newId = "B" . str_pad($num, 3, "0", STR_PAD_LEFT);
    } else {
        $newId = "B001";
    }
    $idStmt->close();

    // Insert new blog using prepared statement
    $insertStmt = $con->prepare("INSERT INTO dental_blogs (blog_id, title, content, published_at, status) VALUES (?, ?, ?, NOW(), 'published')");
    if ($insertStmt) {
        $insertStmt->bind_param("sss", $newId, $title, $content);
        if (!$insertStmt->execute()) {
            error_log("Blog insert failed: " . $insertStmt->error);
        }
        $insertStmt->close();
    }

    // Keep only 5 recent blogs
    $countStmt = $con->prepare("SELECT COUNT(*) AS total FROM dental_blogs");
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    if ($total > 5) {
        $deleteCount = (int)($total - 5);
        // Get IDs of oldest blogs to delete
        $selectStmt = $con->prepare("SELECT blog_id FROM dental_blogs ORDER BY published_at ASC LIMIT ?");
        $selectStmt->bind_param("i", $deleteCount);
        $selectStmt->execute();
        $selectResult = $selectStmt->get_result();
        $idsToDelete = [];
        while ($row = $selectResult->fetch_assoc()) {
            $idsToDelete[] = $row['blog_id'];
        }
        $selectStmt->close();
        
        // Delete the old blogs
        if (!empty($idsToDelete)) {
            $placeholders = str_repeat('?,', count($idsToDelete) - 1) . '?';
            $deleteStmt = $con->prepare("DELETE FROM dental_blogs WHERE blog_id IN ($placeholders)");
            $deleteStmt->bind_param(str_repeat('s', count($idsToDelete)), ...$idsToDelete);
            $deleteStmt->execute();
            $deleteStmt->close();
        }
    }
}

// Fetch blogs using prepared statement (limit to 5)
$blogsStmt = $con->prepare("SELECT * FROM dental_blogs ORDER BY published_at DESC LIMIT 5");
$blogsStmt->execute();
$blogs = $blogsStmt->get_result();

// Function to generate dynamic label based on title/content
function getBlogLabel($title, $content) {
    // Ensure we have valid strings
    if (empty($title) && empty($content)) {
        return 'Dental Care';
    }
    
    $titleLower = strtolower(trim($title));
    $contentLower = strtolower(trim($content));
    $combined = $titleLower . ' ' . $contentLower;
    
    // Check for keywords to determine category (order matters - most specific first)
    if (stripos($combined, 'whiten') !== false || stripos($combined, 'bleach') !== false || stripos($combined, 'sparkle') !== false || stripos($combined, 'shine') !== false) {
        return 'Teeth Whitening';
    } elseif (stripos($combined, 'cavity') !== false || stripos($combined, 'decay') !== false || stripos($combined, 'caries') !== false) {
        return 'Cavity Prevention';
    } elseif (stripos($combined, 'gum') !== false || stripos($combined, 'gingivitis') !== false || stripos($combined, 'periodontal') !== false) {
        return 'Gum Health';
    } elseif (stripos($combined, 'brush') !== false || stripos($combined, 'floss') !== false || stripos($combined, 'hygiene') !== false || stripos($combined, 'cleaning') !== false) {
        return 'Oral Hygiene';
    } elseif (stripos($combined, 'orthodont') !== false || stripos($combined, 'braces') !== false || stripos($combined, 'align') !== false) {
        return 'Orthodontics';
    } elseif (stripos($combined, 'implant') !== false || stripos($combined, 'extraction') !== false || stripos($combined, 'surgery') !== false) {
        return 'Dental Procedures';
    } elseif (stripos($combined, 'child') !== false || stripos($combined, 'kid') !== false || stripos($combined, 'pediatric') !== false) {
        return 'Pediatric Care';
    } elseif (stripos($combined, 'emergency') !== false || stripos($combined, 'pain') !== false || stripos($combined, 'toothache') !== false) {
        return 'Emergency Care';
    } elseif (stripos($combined, 'prevent') !== false || stripos($combined, 'tip') !== false || stripos($combined, 'advice') !== false) {
        return 'Health Tips';
    } else {
        return 'Dental Care';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Clinic Blog</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            margin: 0;
            padding-bottom: 40px;
            min-height: 100vh;
        }

        .blog-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 20px;
            text-align: center;
            margin-bottom: 50px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .blog-header h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .blog-header p {
            font-size: 1.3rem;
            font-weight: 300;
            opacity: 0.95;
        }

        .blog-header .icon {
            font-size: 3.5rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .blog-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 30px;
        }

        .blog-card {
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0,0,0,0.05);
            position: relative;
        }

        .blog-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .blog-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .blog-card:hover::before {
            transform: scaleX(1);
        }

        .blog-card-header {
            padding: 25px 25px 15px;
            border-bottom: 1px solid #f0f0f0;
        }

        .blog-card-header .blog-category {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .blog-card-header h2 {
            color: #2d3748;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .blog-content {
            padding: 20px 25px;
            flex-grow: 1;
        }

        .blog-content p {
            color: #4a5568;
            line-height: 1.8;
            margin: 0;
            font-size: 0.95rem;
            display: -webkit-box;
            -webkit-line-clamp: 4;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .blog-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 25px;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        .blog-date {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.875rem;
            gap: 8px;
        }

        .blog-date i {
            color: #667eea;
        }

        .read-more {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .read-more:hover {
            color: #764ba2;
            gap: 10px;
        }

        .read-more i {
            transition: transform 0.3s ease;
        }

        .read-more:hover i {
            transform: translateX(4px);
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 5rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #4a5568;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #718096;
            font-size: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: #fff;
            padding: 40px;
            border-radius: 20px;
            max-width: 700px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h2 {
            color: #2d3748;
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            flex: 1;
            padding-right: 20px;
            line-height: 1.3;
        }

        .close {
            color: #a0aec0;
            font-size: 28px;
            font-weight: 300;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f7fafc;
        }

        .close:hover {
            color: #e53e3e;
            background: #fed7d7;
            transform: rotate(90deg);
        }

        .modal-body {
            color: #4a5568;
            line-height: 1.9;
            font-size: 1.05rem;
            white-space: pre-wrap;
        }

        .modal-body p {
            margin-bottom: 15px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .blog-header h1 {
                font-size: 2rem;
            }

            .blog-header p {
                font-size: 1rem;
            }

            .blog-container {
                grid-template-columns: 1fr;
                gap: 20px;
                padding: 0 15px;
            }

            .blog-card-header h2 {
                font-size: 1.3rem;
            }

            .modal-content {
                padding: 25px;
                max-height: 90vh;
            }

            .modal-header h2 {
                font-size: 1.5rem;
            }
        }

        /* Scrollbar styling for modal */
        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <div class="blog-header">
        <div class="icon">
            <i class="fas fa-tooth"></i>
        </div>
        <h1>Dental Health Blog</h1>
        <p>Expert tips, advice, and insights for a healthier smile</p>
    </div>

    <div class="blog-container">
        <?php 
        $blogCount = 0;
        while ($row = mysqli_fetch_assoc($blogs)): 
            $blogCount++;
            $contentPreview = htmlspecialchars(substr(strip_tags($row['content']), 0, 150));
            $publishedDate = date("F d, Y", strtotime($row['published_at']));
            $blogLabel = getBlogLabel($row['title'], $row['content']);
            // Ensure label is always a clean, complete string
            $blogLabel = trim($blogLabel);
            if (empty($blogLabel) || strlen($blogLabel) < 3) {
                $blogLabel = 'Dental Care';
            }
        ?>
            <div class="blog-card">
                <div class="blog-card-header">
                    <span class="blog-category">
                        <i class="fas fa-tag"></i> <?= htmlspecialchars($blogLabel) ?>
                    </span>
                    <h2><?= htmlspecialchars($row['title']) ?></h2>
                </div>
                <div class="blog-content">
                    <p><?= $contentPreview ?><?= strlen(strip_tags($row['content'])) > 150 ? '...' : '' ?></p>
                </div>
                <div class="blog-footer">
                    <div class="blog-date">
                        <i class="far fa-calendar-alt"></i>
                        <span><?= $publishedDate ?></span>
                    </div>
                    <a href="#"
                       class="read-more"
                       data-title="<?= htmlspecialchars($row['title']) ?>"
                       data-content="<?= htmlspecialchars(nl2br($row['content'])) ?>">
                        Read More
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        <?php endwhile; ?>
        
        <?php if ($blogCount == 0): ?>
            <div class="empty-state">
                <i class="fas fa-blog"></i>
                <h3>No Blog Posts Yet</h3>
                <p>Check back soon for helpful dental health tips and advice!</p>
            </div>
        <?php endif; ?>
    </div>

    <div id="blogModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="modalContent"></div>
        </div>
    </div>

    <script>
        const modal = document.getElementById("blogModal");
        const modalTitle = document.getElementById("modalTitle");
        const modalContent = document.getElementById("modalContent");
        const closeModal = document.querySelector(".close");

        document.querySelectorAll(".read-more").forEach(link => {
            link.addEventListener("click", (e) => {
                e.preventDefault();
                const title = link.getAttribute("data-title");
                const content = link.getAttribute("data-content");
                
                modalTitle.textContent = title;
                modalContent.innerHTML = content;
                modal.style.display = "flex";
                document.body.style.overflow = "hidden";
            });
        });

        function closeModalFunc() {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        }

        closeModal.onclick = closeModalFunc;
        
        window.onclick = (event) => {
            if (event.target === modal) {
                closeModalFunc();
            }
        };

        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display === 'flex') {
                closeModalFunc();
            }
        });
    </script>

    <?php include_once('../layouts/footer.php'); ?>
</body>
</html>

<?php mysqli_close($con); ?>
