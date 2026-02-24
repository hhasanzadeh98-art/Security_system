<?php
// test_report_create.php

// فقط این یک require کافی است (چون همه کلاس‌ها داخل classes.php هستند)
require_once 'classes.php';

try {
    // اتصال PDO - اگر در Database::getConnection() تعریف شده، از همان استفاده کن
    // در غیر این صورت مستقیم بساز (موقتی برای تست)
    $pdo = Database::getConnection();   // ← بهترین گزینه (اگر کار می‌کنه)

    // اگر خطای بالا دادی، موقتاً این را استفاده کن:
    // $pdo = new PDO(
    //     "mysql:host=localhost;dbname=security_db;charset=utf8mb4",
    //     "root", "", [
    //         PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    //         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    //     ]
    // );

    $reportModel = new GuardShiftReport($pdo);

    // داده تست (guard_id را حتماً با یک مقدار واقعی از جدول users عوض کن)
    $testData = [
        'report_date'         => '2025-11-03',
        'jalali_year'         => 1404,
        'jalali_month'        => 8,
        'jalali_day'          => 12,
        'guard_id'            => 2,               // ← اینجا ID واقعی بگذار (مثلاً 1 یا 2 یا ...)
        'shift_type'          => '24h',
        'appearance'          => 1,
        'vehicle_control'     => 1,
        'property_control'    => 1,
        'camera_monitoring'   => 1,
        'fire_safety'         => 1,
        'building_check'      => 1,
        'alarm_system'        => 1,
        'fingerprint'         => 1,
        'night_rounds'        => 1,
        'incidents_text'      => 'تست آزمایشی - بدون مشکل',
    ];

    $newId = $reportModel->create($testData);

    if ($newId !== false && $newId > 0) {
        echo "<div style='color:green; font-size:1.3em; padding:20px;'>";
        echo "ثبت موفق شد ✓<br>";
        echo "شناسه رکورد جدید: <strong>$newId</strong>";
        echo "</div>";
    } else {
        echo "<div style='color:red; font-size:1.3em; padding:20px;'>";
        echo "ثبت انجام نشد (false برگشت)";
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<div style='color:red; font-weight:bold; padding:20px; border:2px solid red;'>";
    echo "خطا رخ داد:<br>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "</div>";
}
