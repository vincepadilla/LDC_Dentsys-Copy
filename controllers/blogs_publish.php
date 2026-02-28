<?php

    include_once("generate_blogs.php");

    try {
        $pdo = getPdoConnection(); 
    } catch (Exception $e) {
        
        exit(1); 
    }

    $ch = curl_init(GEMINI_URL);

    $payload = json_encode([
        'contents' => [['parts' => [['text' => BLOG_PROMPT]]]],
        'config' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'object',
                'properties' => ['title' => ['type' => 'string'], 'content' => ['type' => 'string']],
                'required' => ['title', 'content']
            ]
        ]
    ]);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        error_log("CRON FAILED: Gemini API cURL error or no response.");
        exit(1);
    }

    $data = json_decode($response, true);
    $generated_text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    $blog_data = json_decode($generated_text, true);

    if (!$blog_data || !isset($blog_data['title'], $blog_data['content'])) {
        error_log("CRON FAILED: Failed to parse JSON response from Gemini. Raw output: " . $generated_text);
        exit(1);
    }

    $blog_title = trim($blog_data['title']);
    $blog_content = trim($blog_data['content']);

    //INSERT NEW BLOG POST
    $sql_insert = "
        INSERT INTO dental_blogs (title, content, published_at, status)
        VALUES (:title, :content, NOW(), 'published')
    ";

    try {
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute(['title' => $blog_title, 'content' => $blog_content]);
        error_log("CRON SUCCESS: Published new blog post.");
    } catch (\PDOException $e) {
        error_log("CRON FAILED: Database insert failed: " . $e->getMessage());
        exit(1);
    }

    //ENFORCE BLOG LIMIT (DELETE OLDEST)

    $sql_delete = "
        DELETE FROM dental_blogs
        WHERE id NOT IN (
            SELECT id FROM (
                SELECT id FROM dental_blogs
                ORDER BY published_at DESC
                LIMIT " . BLOG_LIMIT . "
            ) AS keep_posts
        )
    ";

    try {
        $deleted_count = $pdo->exec($sql_delete);
        if ($deleted_count > 0) {
            error_log("CLEANUP SUCCESS: Deleted $deleted_count old blog post(s).");
        }
    } catch (\PDOException $e) {
        error_log("CLEANUP FAILED: Failed to delete old blog posts: " . $e->getMessage());
    }

?>