<?php
class AdminDataController {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    // ==================== DASHBOARD METHODS ====================
    
    public function getDashboardStats() {
        $stats = [];
        
        // Total appointments
        $stats['total_appointments'] = $this->fetchSingle("SELECT COUNT(*) FROM appointments");
        
        // Active dentists
        $stats['active_dentists'] = $this->fetchSingle("SELECT COUNT(*) FROM multidisciplinary_dental_team WHERE status = 'active'");
        
        // Total services
        $stats['total_services'] = $this->fetchSingle("SELECT COUNT(*) FROM services");
        
        // Today's appointments
        $stats['todays_appointments'] = $this->fetchSingle(
            "SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status != 'Cancelled'"
        );
        
        return $stats;
    }
    
    public function getTodayAppointments() {
        $sql = "SELECT a.appointment_id, a.appointment_time, a.status,
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       s.service_category,
                       CONCAT(d.first_name, ' ', d.last_name) as dentist_name
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                WHERE a.appointment_date = CURDATE() AND a.status != 'Cancelled'
                ORDER BY a.appointment_time ASC";
        
        return $this->fetchAll($sql);
    }
    
    public function getUpcomingAppointments($limit = 5) {
        $limit = (int)$limit;

        $sql = "SELECT a.appointment_id, a.appointment_date, a.appointment_time,
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                WHERE a.appointment_date > CURDATE() AND a.status != 'Cancelled'
                ORDER BY a.appointment_date ASC, a.appointment_time ASC
                LIMIT $limit";

        return $this->fetchAll($sql);
    }
    
    public function getAppointmentHours() {
        $result = mysqli_query($this->conn, 
            "SELECT HOUR(appointment_time) AS hour 
             FROM appointments 
             WHERE appointment_date = CURDATE() 
             GROUP BY HOUR(appointment_time) 
             ORDER BY hour");
        
        $hours = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $hours[] = $row['hour'] . ':00';
        }
        
        return $hours;
    }
    
    public function getAppointmentCounts() {
        $result = mysqli_query($this->conn, 
            "SELECT HOUR(appointment_time) AS hour, COUNT(*) AS total 
             FROM appointments 
             WHERE appointment_date = CURDATE() 
             GROUP BY HOUR(appointment_time) 
             ORDER BY hour");
        
        $counts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[] = (int)$row['total'];
        }
        
        return $counts;
    }
    
    // ==================== APPOINTMENT METHODS ====================
    
    public function getAllAppointments() {
        $sql = "SELECT a.*, p.patient_id, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       s.service_category, s.sub_service,
                       CONCAT(d.first_name, ' ', d.last_name) as dentist_name
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                ORDER BY a.appointment_date DESC, a.appointment_time DESC";
        
        return $this->fetchAll($sql);
    }
    
    public function getAppointmentById($appointmentId) {
        $sql = "SELECT a.*, p.patient_id, p.first_name as patient_first, p.last_name as patient_last,
                       s.service_category, s.sub_service,
                       d.first_name as dentist_first, d.last_name as dentist_last
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                WHERE a.appointment_id = ?";
        
        return $this->fetchSinglePrepared($sql, [$appointmentId]);
    }
    
    // ==================== PATIENT METHODS ====================
    
    public function getAllPatients() {
        $sql = "SELECT * FROM patient_information ORDER BY patient_id ASC";
        return $this->fetchAll($sql);
    }
    
    public function getPatientsMap() {
        $sql = "SELECT patient_id, CONCAT(first_name, ' ', last_name) as full_name 
                FROM patient_information 
                ORDER BY patient_id ASC";
        
        $result = mysqli_query($this->conn, $sql);
        $patientsMap = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $patientsMap[$row['patient_id']] = $row['full_name'];
        }
        
        return $patientsMap;
    }
    
    public function getPatientById($patientId) {
        $sql = "SELECT * FROM patient_information WHERE patient_id = ?";
        return $this->fetchSinglePrepared($sql, [$patientId]);
    }
    
    public function getPatientTreatmentHistory($patientId) {
        $sql = "SELECT * FROM treatment_history 
                WHERE patient_id = ? 
                ORDER BY created_at DESC";
        
        return $this->fetchAllPrepared($sql, [$patientId]);
    }
    
    public function getPatientAppointments($patientId) {
        $sql = "SELECT a.*, s.service_category, 
                       CONCAT(d.first_name, ' ', d.last_name) as dentist_name
                FROM appointments a
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                WHERE a.patient_id = ? 
                ORDER BY a.appointment_date DESC";
        
        return $this->fetchAllPrepared($sql, [$patientId]);
    }
    
    public function getPatientLastTransaction($patientId) {
        $sql = "SELECT p.* 
                FROM payment p
                INNER JOIN appointments a ON p.appointment_id = a.appointment_id
                WHERE a.patient_id = ? 
                ORDER BY p.payment_id DESC 
                LIMIT 1";
        
        return $this->fetchSinglePrepared($sql, [$patientId]);
    }
    
    // ==================== SERVICE METHODS ====================
    
    public function getServicesList() {
        $sql = "SELECT * FROM services ORDER BY service_category, service_id";
        return $this->fetchAll($sql);
    }
    
    public function getServiceCategories() {
        $sql = "SELECT DISTINCT service_category FROM services 
                WHERE service_category IS NOT NULL AND service_category != '' 
                ORDER BY service_category";
        
        $result = mysqli_query($this->conn, $sql);
        $categories = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row['service_category'];
        }
        
        return $categories;
    }
    
    public function getServiceById($serviceId) {
        $sql = "SELECT * FROM services WHERE service_id = ?";
        return $this->fetchSinglePrepared($sql, [$serviceId]);
    }
    
    public function getServices() {
        $sql = "SELECT service_id, service_category, sub_service FROM services ORDER BY service_category";
        return $this->fetchAll($sql);
    }
    
    // ==================== DENTIST METHODS ====================
    
    public function getAllDentists() {
        $sql = "SELECT * FROM multidisciplinary_dental_team ORDER BY status DESC, team_id";
        return $this->fetchAll($sql);
    }
    
    public function getActiveDentists() {
        $sql = "SELECT team_id, first_name, last_name FROM multidisciplinary_dental_team WHERE status = 'active'";
        return $this->fetchAll($sql);
    }
    
    public function getDentistById($teamId) {
        $sql = "SELECT * FROM multidisciplinary_dental_team WHERE team_id = ?";
        return $this->fetchSinglePrepared($sql, [$teamId]);
    }
    
    public function getAdminUsers() {
        $sql = "SELECT user_id, first_name, last_name, email, phone 
                FROM users 
                WHERE role IN ('admin', 'staff', 'dentist') 
                ORDER BY user_id";
        
        return $this->fetchAll($sql);
    }
    
    // ==================== PAYMENT METHODS ====================
    
    public function getPaymentTransactions() {
        $sql = "SELECT p.*, a.patient_id, a.appointment_date
                FROM payment p
                LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
                ORDER BY a.appointment_date DESC, p.payment_id DESC";
        
        return $this->fetchAll($sql);
    }
    
    public function getPaymentMethods() {
        $sql = "SELECT DISTINCT method FROM payment 
                WHERE method IS NOT NULL AND method != '' 
                ORDER BY method";
        
        $result = mysqli_query($this->conn, $sql);
        $methods = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $methods[] = $row['method'];
        }
        
        return $methods;
    }
    
    public function getPaymentStatuses() {
        $sql = "SELECT DISTINCT status FROM payment 
                WHERE status IS NOT NULL AND status != '' 
                ORDER BY 
                CASE status 
                    WHEN 'pending' THEN 1 
                    WHEN 'paid' THEN 2 
                    WHEN 'failed' THEN 3 
                    WHEN 'refunded' THEN 4 
                    ELSE 5 
                END";
        
        $result = mysqli_query($this->conn, $sql);
        $statuses = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $statuses[] = $row['status'];
        }
        
        return $statuses;
    }
    
    // ==================== TREATMENT HISTORY METHODS ====================
    
    public function getTreatmentHistory() {
        $sql = "SELECT th.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM treatment_history th
                LEFT JOIN patient_information p ON th.patient_id = p.patient_id
                ORDER BY th.created_at DESC";
        
        return $this->fetchAll($sql);
    }
    
    // ==================== REPORT METHODS ====================
    
    public function getReportData() {
        $data = [];
        
        // Total appointments
        $data['total_appointments'] = $this->fetchSingle("SELECT COUNT(*) FROM appointments");
        
        // Total down payment
        $data['total_downpayment'] = $this->fetchSingle("SELECT IFNULL(SUM(amount), 0) FROM payment WHERE status = 'paid'");
        
        // Today's appointments
        $data['todays_appointments'] = $this->fetchSingle(
            "SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()"
        );
        
        // Total revenue
        $data['total_revenue'] = $this->fetchSingle("SELECT IFNULL(SUM(treatment_cost), 0) FROM treatment_history");
        
        // Appointment status breakdown
        $statusQuery = mysqli_query($this->conn, 
            "SELECT status, COUNT(*) as count 
             FROM appointments 
             GROUP BY status");
        
        $data['appointment_statuses'] = [];
        while ($row = mysqli_fetch_assoc($statusQuery)) {
            $data['appointment_statuses'][$row['status']] = $row['count'];
        }
        
        // Total downpayment by services
        $serviceRevenueQuery = mysqli_query($this->conn, 
            "SELECT s.service_category, SUM(p.amount) as total_amount
             FROM payment p
             INNER JOIN appointments a ON p.appointment_id = a.appointment_id
             INNER JOIN services s ON a.service_id = s.service_id
             WHERE p.status = 'paid'
             GROUP BY s.service_category");
        
        $data['service_revenue'] = [];
        $data['service_revenue_labels'] = [];
        $data['service_revenue_amounts'] = [];
        
        while ($row = mysqli_fetch_assoc($serviceRevenueQuery)) {
            $data['service_revenue'][] = $row;
            $data['service_revenue_labels'][] = $row['service_category'];
            $data['service_revenue_amounts'][] = (float)$row['total_amount'];
        }
        
        // Services availed count
        $servicesAvailedQuery = mysqli_query($this->conn, 
            "SELECT s.sub_service, COUNT(*) as count
             FROM appointments a
             INNER JOIN services s ON a.service_id = s.service_id
             GROUP BY s.sub_service
             ORDER BY count DESC");
        
        $data['services_availed_labels'] = [];
        $data['services_availed_counts'] = [];
        
        while ($row = mysqli_fetch_assoc($servicesAvailedQuery)) {
            $data['services_availed_labels'][] = $row['sub_service'];
            $data['services_availed_counts'][] = (int)$row['count'];
        }
        
        return $data;
    }
    
    public function getMonthlyServiceData() {
        $monthlyData = [];
        $currentYear = date('Y');
        
        for ($month = 1; $month <= 12; $month++) {
            $sql = "SELECT s.service_category, COUNT(*) AS count
                    FROM appointments a
                    LEFT JOIN services s ON a.service_id = s.service_id
                    WHERE MONTH(a.appointment_date) = $month 
                    AND YEAR(a.appointment_date) = $currentYear
                    GROUP BY s.service_category";
            
            $result = mysqli_query($this->conn, $sql);
            $services = [];
            $counts = [];
            
            while ($row = mysqli_fetch_assoc($result)) {
                $services[] = $row['service_category'];
                $counts[] = (int)$row['count'];
            }
            
            $monthlyData[$month] = [
                'labels' => $services,
                'counts' => $counts,
                'total' => array_sum($counts)
            ];
        }
        
        return $monthlyData;
    }
    
    public function getRevenueData() {
        // Get revenue by services from treatment_history
        $revenueQuery = mysqli_query($this->conn, 
            "SELECT th.treatment,
                    SUM(th.treatment_cost) as total_revenue,
                    COUNT(*) as treatment_count
             FROM treatment_history th
             WHERE th.treatment_cost > 0
             GROUP BY th.treatment
             ORDER BY total_revenue DESC");
        
        $serviceNames = [];
        $serviceRevenues = [];
        $treatmentCounts = [];
        $totalRevenue = 0;
        
        while ($row = mysqli_fetch_assoc($revenueQuery)) {
            $serviceNames[] = $row['treatment'];
            $serviceRevenues[] = (float)$row['total_revenue'];
            $treatmentCounts[] = (int)$row['treatment_count'];
            $totalRevenue += $row['total_revenue'];
        }
        
        return [
            'service_names' => $serviceNames,
            'service_revenues' => $serviceRevenues,
            'treatment_counts' => $treatmentCounts,
            'total_revenue' => $totalRevenue
        ];
    }
    
    public function getAppointmentsPerDay($days = 30) {
        $sql = "SELECT appointment_date, COUNT(*) as count 
                FROM appointments 
                WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY appointment_date 
                ORDER BY appointment_date";
        
        $result = mysqli_query($this->conn, $sql);
        $dates = [];
        $counts = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $dates[] = date('M j', strtotime($row['appointment_date']));
            $counts[] = (int)$row['count'];
        }
        
        return [
            'dates' => $dates,
            'counts' => $counts
        ];
    }
    
    // ==================== SCHEDULE METHODS ====================
    
    public function getBlockedTimeSlots($dentistId = null) {
        $baseSql = "SELECT bs.*, 
                       CONCAT(d.first_name, ' ', d.last_name) as dentist_name
                FROM blocked_slots bs
                LEFT JOIN multidisciplinary_dental_team d ON bs.dentist_id = d.team_id";

        $orderClause = " ORDER BY bs.block_date DESC";

        if ($dentistId) {
            $sql = $baseSql . " WHERE bs.dentist_id = ?" . $orderClause;
            return $this->fetchAllPrepared($sql, [$dentistId]);
        }

        $sql = $baseSql . $orderClause;
        return $this->fetchAll($sql);
    }
    
    public function getHolidays() {
        $sql = "SELECT * FROM holidays 
                WHERE holiday_date >= CURDATE() 
                ORDER BY holiday_date ASC";
        
        return $this->fetchAll($sql);
    }
    
    public function getClosures() {
        $sql = "SELECT * FROM clinic_closures 
                WHERE closure_date >= CURDATE() 
                ORDER BY closure_date ASC";
        
        return $this->fetchAll($sql);
    }
    
    public function getDentistSchedule($dentistId, $startDate, $endDate) {
        $sql = "SELECT a.appointment_date, a.appointment_time, 
                       a.status, a.appointment_id,
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                WHERE a.team_id = ? 
                AND a.appointment_date BETWEEN ? AND ?
                AND a.status NOT IN ('Cancelled', 'No-Show')
                ORDER BY a.appointment_date, a.appointment_time";
        
        return $this->fetchAllPrepared($sql, [$dentistId, $startDate, $endDate]);
    }
    
    public function getAvailableTimeSlots($date, $dentistId = null) {
        $sql = "SELECT DISTINCT appointment_time 
                FROM appointments 
                WHERE appointment_date = ? 
                AND status NOT IN ('Cancelled', 'No-Show')";
        
        $params = [$date];
        
        if ($dentistId) {
            $sql .= " AND team_id = ?";
            $params[] = $dentistId;
        }
        
        $result = $this->fetchAllPrepared($sql, $params);
        $bookedSlots = [];
        
        foreach ($result as $row) {
            $bookedSlots[] = $row['appointment_time'];
        }
        
        // Get all possible time slots
        $allSlots = [
            'firstBatch', 'secondBatch', 'thirdBatch', 'fourthBatch',
            'fifthBatch', 'sixthBatch', 'sevenBatch', 'eightBatch',
            'nineBatch', 'tenBatch', 'lastBatch'
        ];
        
        // Return available slots (not booked)
        $availableSlots = array_diff($allSlots, $bookedSlots);
        
        return array_values($availableSlots);
    }
    
    // ==================== UTILITY METHODS ====================
    
    private function fetchAll($sql) {
        $result = mysqli_query($this->conn, $sql);
        $data = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }
        
        return $data;
    }
    
    private function fetchAllPrepared($sql, $params) {
        $stmt = mysqli_prepare($this->conn, $sql);

        if ($stmt) {
            if (!empty($params)) {
                $this->bindParams($stmt, $params);
            }

            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $data = [];

            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $data[] = $row;
                }
            }

            mysqli_stmt_close($stmt);
            return $data;
        }

        return [];
    }
    
    private function fetchSingle($sql) {
        $result = mysqli_query($this->conn, $sql);
        
        if ($result && $row = mysqli_fetch_row($result)) {
            return $row[0] ?? 0;
        }
        
        return 0;
    }
    
    private function fetchSinglePrepared($sql, $params) {
        $stmt = mysqli_prepare($this->conn, $sql);

        if ($stmt) {
            if (!empty($params)) {
                $this->bindParams($stmt, $params);
            }

            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result) {
                $data = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if ($data === null) {
                    return null;
                }

                // If single column, return scalar value for convenience
                if (count($data) === 1) {
                    return array_shift($data);
                }

                return $data;
            }

            mysqli_stmt_close($stmt);
            return null;
        }

        return null;
    }

    // Helper to bind params safely (mysqli requires references)
    private function bindParams($stmt, $params) {
        if (empty($params)) return;

        $types = str_repeat('s', count($params));

        // Create references
        $refs = [];
        foreach ($params as $key => $val) {
            $refs[$key] = &$params[$key];
        }

        array_unshift($refs, $types);
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    
    // ==================== UPDATE METHODS ====================
    
    public function updateAppointmentStatus($appointmentId, $status, $notes = '') {
        $sql = "UPDATE appointments SET status = ?, updated_at = NOW()";
        
        if ($notes) {
            $sql .= ", notes = CONCAT(IFNULL(notes, ''), '\n', ?)";
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 'ss', $status, $notes);
        } else {
            $stmt = mysqli_prepare($this->conn, $sql);
            mysqli_stmt_bind_param($stmt, 's', $status);
        }
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function rescheduleAppointment($appointmentId, $newDate, $newTime) {
        $sql = "UPDATE appointments 
                SET appointment_date = ?, appointment_time = ?, 
                    status = 'Rescheduled', updated_at = NOW() 
                WHERE appointment_id = ?";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sss', $newDate, $newTime, $appointmentId);
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function saveTreatment($patientId, $appointmentId, $treatment, $prescription, $notes, $cost) {
        $sql = "INSERT INTO treatment_history 
                (patient_id, appointment_id, treatment, prescription_given, notes, treatment_cost) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssssd', $patientId, $appointmentId, $treatment, $prescription, $notes, $cost);
        
        if (mysqli_stmt_execute($stmt)) {
            // Update appointment status to completed
            $this->updateAppointmentStatus($appointmentId, 'Completed');
            return true;
        }
        
        return false;
    }
    
    public function updatePatient($patientId, $data) {
        $sql = "UPDATE patient_information SET 
                first_name = ?, last_name = ?, birthdate = ?, 
                gender = ?, email = ?, phone = ?, address = ? 
                WHERE patient_id = ?";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssssss', 
            $data['first_name'], $data['last_name'], $data['birthdate'],
            $data['gender'], $data['email'], $data['phone'], $data['address'], $patientId);
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function archivePatient($patientId) {
        $sql = "UPDATE patient_information SET status = 'archived', updated_at = NOW() WHERE patient_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $patientId);
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function updatePaymentStatus($paymentId, $status) {
        $sql = "UPDATE payment SET status = ?, updated_at = NOW() WHERE payment_id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ss', $status, $paymentId);
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function blockTimeSlot($dentistId, $date, $timeSlot, $reason) {
        $sql = "INSERT INTO blocked_slots (dentist_id, block_date, time_slot, reason) 
                VALUES (?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssss', $dentistId, $date, $timeSlot, $reason);
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function addHoliday($name, $date, $recurrence) {
        $sql = "INSERT INTO holidays (holiday_name, holiday_date, recurrence) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sss', $name, $date, $recurrence);
        
        return mysqli_stmt_execute($stmt);
    }
    
    public function addClosure($startDate, $endDate, $type, $reason, $notifyPatients = false) {
        $sql = "INSERT INTO clinic_closures (start_date, end_date, closure_type, reason, notify_patients) 
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssssi', $startDate, $endDate, $type, $reason, $notifyPatients);
        
        return mysqli_stmt_execute($stmt);
    }
    
    // ==================== STATISTICS METHODS ====================
    
    public function getMonthlyStats($year, $month) {
        $stats = [];
        
        // Total appointments for month
        $stats['total_appointments'] = $this->fetchSinglePrepared(
            "SELECT COUNT(*) FROM appointments 
             WHERE YEAR(appointment_date) = ? AND MONTH(appointment_date) = ?",
            [$year, $month]
        );
        
        // Total revenue for month
        $stats['total_revenue'] = $this->fetchSinglePrepared(
            "SELECT IFNULL(SUM(treatment_cost), 0) FROM treatment_history 
             WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?",
            [$year, $month]
        );
        
        // New patients for month
        $stats['new_patients'] = $this->fetchSinglePrepared(
            "SELECT COUNT(*) FROM patient_information 
             WHERE YEAR(created_at) = ? AND MONTH(created_at) = ?",
            [$year, $month]
        );
        
        // Most popular service
        $popularService = $this->fetchSinglePrepared(
            "SELECT s.service_category, COUNT(*) as count
             FROM appointments a
             LEFT JOIN services s ON a.service_id = s.service_id
             WHERE YEAR(a.appointment_date) = ? AND MONTH(a.appointment_date) = ?
             GROUP BY s.service_category
             ORDER BY count DESC
             LIMIT 1",
            [$year, $month]
        );
        
        $stats['popular_service'] = $popularService ?: 'None';
        
        return $stats;
    }
    
    public function getYearlyStats($year) {
        $stats = [];
        
        for ($month = 1; $month <= 12; $month++) {
            $monthStats = $this->getMonthlyStats($year, $month);
            $stats[$month] = $monthStats;
        }
        
        return $stats;
    }
    
    // ==================== SEARCH METHODS ====================
    
    public function searchPatients($keyword) {
        $sql = "SELECT * FROM patient_information 
                WHERE CONCAT(first_name, ' ', last_name) LIKE ? 
                   OR patient_id LIKE ? 
                   OR email LIKE ? 
                   OR phone LIKE ?
                ORDER BY patient_id DESC
                LIMIT 50";
        
        $searchTerm = "%$keyword%";
        return $this->fetchAllPrepared($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    public function searchAppointments($keyword, $date = null) {
        $sql = "SELECT a.*, CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                WHERE CONCAT(p.first_name, ' ', p.last_name) LIKE ? 
                   OR a.appointment_id LIKE ?";
        
        $params = ["%$keyword%", "%$keyword%"];
        
        if ($date) {
            $sql .= " AND a.appointment_date = ?";
            $params[] = $date;
        }
        
        $sql .= " ORDER BY a.appointment_date DESC LIMIT 50";
        
        return $this->fetchAllPrepared($sql, $params);
    }
    
    // ==================== VALIDATION METHODS ====================
    
    public function isTimeSlotAvailable($date, $timeSlot, $dentistId = null) {
        $sql = "SELECT COUNT(*) FROM appointments 
                WHERE appointment_date = ? 
                AND appointment_time = ? 
                AND status NOT IN ('Cancelled', 'No-Show')";
        
        $params = [$date, $timeSlot];
        
        if ($dentistId) {
            $sql .= " AND team_id = ?";
            $params[] = $dentistId;
        }
        
        $count = $this->fetchSinglePrepared($sql, $params);
        return $count === 0;
    }
    
    public function isDateBlocked($date, $dentistId = null) {
        $sql = "SELECT COUNT(*) FROM blocked_slots 
                WHERE block_date = ?";
        
        $params = [$date];
        
        if ($dentistId) {
            $sql .= " AND dentist_id = ?";
            $params[] = $dentistId;
        }
        
        $count = $this->fetchSinglePrepared($sql, $params);
        return $count > 0;
    }
    
    public function isHoliday($date) {
        $sql = "SELECT COUNT(*) FROM holidays 
                WHERE holiday_date = ? 
                OR (recurrence = 'yearly' AND DAY(holiday_date) = DAY(?) AND MONTH(holiday_date) = MONTH(?))";
        
        return $this->fetchSinglePrepared($sql, [$date, $date, $date]) > 0;
    }
    
    public function isClosure($date) {
        $sql = "SELECT COUNT(*) FROM clinic_closures 
                WHERE ? BETWEEN start_date AND end_date";
        
        return $this->fetchSinglePrepared($sql, [$date]) > 0;
    }
    
    // ==================== BULK OPERATIONS ====================
    
    public function cancelAllAppointmentsForDate($date, $reason = 'Clinic Closure') {
        // Get all appointments for the date
        $appointments = $this->fetchAllPrepared(
            "SELECT appointment_id, patient_id FROM appointments 
             WHERE appointment_date = ? AND status NOT IN ('Cancelled', 'Completed', 'No-Show')",
            [$date]
        );
        
        // Cancel each appointment
        foreach ($appointments as $appointment) {
            $this->updateAppointmentStatus($appointment['appointment_id'], 'Cancelled', $reason);
            
            // TODO: Send notification to patient
            // $this->sendCancellationNotification($appointment['patient_id'], $date, $reason);
        }
        
        return count($appointments);
    }
    
    public function sendFollowUpReminders($days = 7) {
        $sql = "SELECT a.appointment_id, a.patient_id, a.appointment_date,
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       p.email, p.phone
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                WHERE a.status = 'Completed'
                AND DATEDIFF(CURDATE(), a.appointment_date) = ?
                AND NOT EXISTS (
                    SELECT 1 FROM follow_up_appointments f 
                    WHERE f.original_appointment_id = a.appointment_id
                )";
        
        $appointments = $this->fetchAllPrepared($sql, [$days]);
        
        foreach ($appointments as $appointment) {
            // TODO: Send follow-up reminder
            // $this->sendEmailOrSMS($appointment['email'], $appointment['phone'], 'followup', $appointment);
        }
        
        return count($appointments);
    }
    
    // ==================== EXPORT METHODS ====================
    
    public function exportAppointments($startDate, $endDate) {
        $sql = "SELECT a.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       s.service_category, s.sub_service,
                       CONCAT(d.first_name, ' ', d.last_name) as dentist_name
                FROM appointments a
                LEFT JOIN patient_information p ON a.patient_id = p.patient_id
                LEFT JOIN services s ON a.service_id = s.service_id
                LEFT JOIN multidisciplinary_dental_team d ON a.team_id = d.team_id
                WHERE a.appointment_date BETWEEN ? AND ?
                ORDER BY a.appointment_date, a.appointment_time";
        
        return $this->fetchAllPrepared($sql, [$startDate, $endDate]);
    }
    
    public function exportPatients() {
        return $this->getAllPatients();
    }
    
    public function exportPayments($startDate, $endDate) {
        $sql = "SELECT p.*, 
                       a.appointment_date,
                       CONCAT(pat.first_name, ' ', pat.last_name) as patient_name
                FROM payment p
                LEFT JOIN appointments a ON p.appointment_id = a.appointment_id
                LEFT JOIN patient_information pat ON a.patient_id = pat.patient_id
                WHERE a.appointment_date BETWEEN ? AND ?
                ORDER BY a.appointment_date DESC";
        
        return $this->fetchAllPrepared($sql, [$startDate, $endDate]);
    }
    
    public function exportTreatmentHistory($patientId = null) {
        $sql = "SELECT th.*, 
                       CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       a.appointment_date
                FROM treatment_history th
                LEFT JOIN patient_information p ON th.patient_id = p.patient_id
                LEFT JOIN appointments a ON th.appointment_id = a.appointment_id";
        
        if ($patientId) {
            $sql .= " WHERE th.patient_id = ?";
            return $this->fetchAllPrepared($sql, [$patientId]);
        }
        
        $sql .= " ORDER BY th.created_at DESC";
        return $this->fetchAll($sql);
    }
}
?>