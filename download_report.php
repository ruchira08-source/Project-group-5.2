<?php
$report = $_GET['report'] ?? 'Report';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . preg_replace('/[^a-zA-Z0-9_]/', '', $report) . '.csv');

$output = fopen('php://output', 'w');

require_once 'db.php';
$data = get_db();

switch ($report) {
    case 'Student_Attendance_Report':
        fputcsv($output, ['PRN', 'Student ID', 'Name', 'Department', 'Total Classes', 'Classes Attended', 'Attendance %']);
        if (isset($data['students'])) {
            foreach ($data['students'] as $s) {
                $total = rand(40, 50);
                $attended = rand(30, $total);
                $pct = round(($attended / $total) * 100, 2) . '%';
                $dept = $s['dept'] ?? ($s['department'] ?? 'Information Technology');
                $prn = $s['prn'] ?? 'N/A';
                fputcsv($output, [$prn, $s['id'], $s['name'], $dept, $total, $attended, $pct]);
            }
        }
        break;
    
    case 'Student_Marks_Report':
        fputcsv($output, ['PRN', 'Student ID', 'Name', 'Department', 'Subject', 'Marks Obtained', 'Max Marks', 'Grade']);
        if (isset($data['students'])) {
            foreach ($data['students'] as $s) {
                $marks = rand(40, 100);
                $grade = $marks >= 90 ? 'A+' : ($marks >= 80 ? 'A' : ($marks >= 70 ? 'B' : ($marks >= 60 ? 'C' : 'D')));
                $dept = $s['dept'] ?? ($s['department'] ?? 'Information Technology');
                $prn = $s['prn'] ?? 'N/A';
                fputcsv($output, [$prn, $s['id'], $s['name'], $dept, 'Core Computer Science', $marks, 100, $grade]);
            }
        }
        break;

    case 'Faculty_Attendance_Report':
        fputcsv($output, ['Faculty ID', 'Name', 'Department', 'Total Working Days', 'Days Present', 'Attendance %']);
        if (isset($data['faculty'])) {
            foreach ($data['faculty'] as $f) {
                $total = 30;
                $present = rand(25, 30);
                $pct = round(($present / $total) * 100, 2) . '%';
                fputcsv($output, [$f['id'], $f['name'], $f['department'], $total, $present, $pct]);
            }
        }
        break;
        
    case 'Assignment_Submission_Report':
        fputcsv($output, ['Student ID', 'Student Name', 'Department', 'Assignment Title', 'Status', 'Date Submitted']);
        if (isset($data['students'])) {
            foreach ($data['students'] as $s) {
                $status = rand(0, 1) ? 'Submitted' : 'Pending';
                $date = $status == 'Submitted' ? date('Y-m-d', strtotime('-' . rand(1, 10) . ' days')) : 'N/A';
                fputcsv($output, [$s['id'], $s['name'], $s['department'], 'Final Project Documentation', $status, $date]);
            }
        }
        break;

    case 'Leave_Report':
        fputcsv($output, ['Faculty ID', 'Faculty Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Status']);
        if (isset($data['faculty'])) {
            foreach ($data['faculty'] as $f) {
                $leaveType = ['Casual Leave', 'Sick Leave', 'Earned Leave'][rand(0, 2)];
                $status = ['Approved', 'Pending', 'Rejected'][rand(0, 2)];
                fputcsv($output, [$f['id'], $f['name'], $f['department'], $leaveType, date('Y-m-d', strtotime('-' . rand(1, 5) . ' days')), date('Y-m-d', strtotime('+' . rand(1, 5) . ' days')), $status]);
            }
        }
        break;

    case 'Grievance_Report':
        fputcsv($output, ['Grievance ID', 'Submitted By', 'Category', 'Description', 'Date', 'Status']);
        fputcsv($output, ['GRV-1001', 'Anonymous', 'Infrastructure', 'Wi-Fi connection is unstable in lab 3', date('Y-m-d', strtotime('-2 days')), 'Resolved']);
        fputcsv($output, ['GRV-1002', 'Tushar Sonar', 'Academic', 'Need extension for final year project deadline', date('Y-m-d', strtotime('-1 days')), 'In Progress']);
        fputcsv($output, ['GRV-1003', 'Student Council', 'Hostel', 'Water supply issues on the 3rd floor', date('Y-m-d'), 'Pending']);
        break;

    case 'Notice_Report':
        fputcsv($output, ['Notice ID', 'Title', 'Date Published', 'Target Audience', 'Status']);
        if (isset($data['notices'])) {
            foreach ($data['notices'] as $n) {
                $id = uniqid('NOT-');
                fputcsv($output, [$id, $n['title'], $n['date'], $n['target_audience'], 'Published']);
            }
        } else {
            fputcsv($output, ['No notices found in database']);
        }
        break;

    case 'Fee_Collection_Report':
        fputcsv($output, ['Student ID', 'Student Name', 'Department', 'Total Fee', 'Amount Paid', 'Due Amount', 'Payment Status']);
        if (isset($data['students'])) {
            foreach ($data['students'] as $s) {
                $totalFee = 50000;
                $isFullyPaid = rand(0, 1);
                $amountPaid = $isFullyPaid ? $totalFee : rand(10000, 40000);
                $due = $totalFee - $amountPaid;
                $status = $isFullyPaid ? 'Cleared' : 'Pending';
                fputcsv($output, [$s['id'], $s['name'], $s['department'], '₹'.$totalFee, '₹'.$amountPaid, '₹'.$due, $status]);
            }
        }
        break;

    default:
        fputcsv($output, ['Error: Report type not found.']);
}

fclose($output);
?>
