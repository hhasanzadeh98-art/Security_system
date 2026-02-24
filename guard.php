<?php
session_start();
require_once 'classes.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guard') {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$userModel = new User();
$currentUser = $userModel->getById($user_id);

if (!$currentUser) {
    session_destroy();
    header("Location: auth.php?error=user_not_found");
    exit();
}

$shiftObj = new Shift();
$active_shift = $shiftObj->getActiveShift($user_id);

$allGuards = $userModel->getAllGuards();
$guards = [];
foreach ($allGuards as $guard) {
    $guards[] = $guard;
}

$today = JalaliDate::getToday();
$todayReportDate = date('Y-m-d');

// چک شیفت امروز
$scheduleModel = new GuardSchedule();
$isOnDuty = $scheduleModel->isGuardOnDutyToday(
    $currentUser->getId(),
    $today->year,
    $today->month,
    $today->day
);

// گزارش امروز
$reportModel = new GuardShiftReport(Database::getConnection());
$existingReport = $reportModel->getByDateAndGuard($todayReportDate, $currentUser->getId());

// وضعیت گزارش امروز برای نمایش در داشبورد
$reportStatus = '';
if ($existingReport) {
    $checkedCount = $existingReport->appearance + $existingReport->vehicle_control + $existingReport->property_control +
        $existingReport->camera_monitoring + $existingReport->fire_safety + $existingReport->building_check +
        $existingReport->alarm_system + $existingReport->after_hours_entry + $existingReport->forbidden_entry +
        $existingReport->aquarium_feed + $existingReport->server_room_status + $existingReport->fingerprint +
        $existingReport->night_rounds;
    $percent = round(($checkedCount / 13) * 100);
    $reportStatus = "گزارش امروز ثبت شده است (تیک‌ها: $checkedCount از ۱۳ - $percent%)";
    if ($existingReport->handover_time) {
        $reportStatus .= " - تحویل داده شده در ساعت " . $existingReport->handover_time;
    }
} else {
    $reportStatus = "<span style='color:#e74c3c;'>گزارش امروز هنوز ثبت نشده است</span>";
}

// نگهبان فردا
$tomorrow = JalaliDate::gregorianToJalali(
    date('Y', strtotime('+1 day')),
    date('m', strtotime('+1 day')),
    date('d', strtotime('+1 day'))
);
$nextGuardId = $scheduleModel->getGuardForDate($tomorrow->year, $tomorrow->month, $tomorrow->day);
$nextGuardName = $nextGuardId ? $scheduleModel->getGuardName($nextGuardId) : 'نامشخص';

// پردازش فرم گزارش
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_report'])) {
    $data = [
        'report_date'         => $todayReportDate,
        'jalali_year'         => $today->year,
        'jalali_month'        => $today->month,
        'jalali_day'          => $today->day,
        'guard_id'            => $currentUser->getId(),
        'shift_type'          => '24h', // ثابت کردن نوع شیفت به 24 ساعته
        'handover_time'       => $_POST['handover_time'] ?? null,
        'previous_guard_id'   => $_POST['previous_guard_id'] ?? null,

        'appearance'          => isset($_POST['appearance']) ? 1 : 0,
        'vehicle_control'     => isset($_POST['vehicle_control']) ? 1 : 0,
        'property_control'    => isset($_POST['property_control']) ? 1 : 0,
        'camera_monitoring'   => isset($_POST['camera_monitoring']) ? 1 : 0,
        'fire_safety'         => isset($_POST['fire_safety']) ? 1 : 0,
        'building_check'      => isset($_POST['building_check']) ? 1 : 0,
        'alarm_system'        => isset($_POST['alarm_system']) ? 1 : 0,
        'after_hours_entry'   => isset($_POST['after_hours_entry']) ? 1 : 0,
        'forbidden_entry'     => isset($_POST['forbidden_entry']) ? 1 : 0,
        'aquarium_feed'       => isset($_POST['aquarium_feed']) ? 1 : 0,
        'server_room_status'  => isset($_POST['server_room_status']) ? 1 : 0,
        'fingerprint'         => isset($_POST['fingerprint']) ? 1 : 0,
        'night_rounds'        => isset($_POST['night_rounds']) ? 1 : 0,

        'incidents_text'      => trim($_POST['incidents_text'] ?? ''),
        'contacts_text'       => trim($_POST['contacts_text'] ?? ''),
        'notes_text'          => trim($_POST['notes_text'] ?? ''),

        'handover_signature'  => trim($_POST['handover_signature'] ?? $currentUser->getName()),
        'received_signature'  => trim($_POST['received_signature'] ?? ''),
    ];

    if ($existingReport) {
        if ($reportModel->update($existingReport->id, $data)) {
            header("Location: guard.php?msg=گزارش+با+موفقیت+ویرایش+شد");
            exit();
        } else {
            header("Location: guard.php?err=خطا+در+ویرایش+گزارش");
            exit();
        }
    } else {
        $newId = $reportModel->create($data);
        if ($newId !== false && $newId > 0) {
            header("Location: guard.php?msg=گزارش+با+موفقیت+ثبت+شد");
            exit();
        } else {
            header("Location: guard.php?err=خطا+در+ثبت+گزارش");
            exit();
        }
    }
}

$gregorianParts = JalaliDate::jalaliToGregorian($today->year, $today->month, $today->day);
$guardCount = count($guards);
$scheduledGuardId = null;
$todayGuard = null;

if ($guardCount > 0) {
    $dayOfYear = JalaliDate::getDayOfYear($today->year, $today->month, $today->day);
    $guardIndex = ($dayOfYear - 1) % $guardCount;
    $scheduledGuardId = $guards[$guardIndex]->getId();
    $todayGuard = $guards[$guardIndex];
}

$isScheduledDay = ($scheduledGuardId == $user_id);

$hasExtraShift = false;
if ($active_shift) {
    $shiftStartTime = strtotime($active_shift->getStartTime());
    $shiftGregorian = date('Y-m-d', $shiftStartTime);
    $shiftParts = explode('-', $shiftGregorian);
    $shiftJalali = JalaliDate::gregorianToJalali($shiftParts[0], $shiftParts[1], $shiftParts[2]);

    $shiftDayOfYear = JalaliDate::getDayOfYear($shiftJalali->year, $shiftJalali->month, $shiftJalali->day);
    $shiftGuardIndex = ($shiftDayOfYear - 1) % $guardCount;
    $shiftScheduledGuardId = $guards[$shiftGuardIndex]->getId();

    $hasExtraShift = ($shiftScheduledGuardId != $user_id);
}

if (isset($_POST['start_shift'])) {
    if ($isScheduledDay || isset($_POST['is_extra'])) {
        if ($shiftObj->startShift($user_id)) {
            header("Location: guard.php");
            exit();
        }
    }
}

if (isset($_POST['end_shift']) && $active_shift) {
    if ($shiftObj->endShift($user_id)) {
        header("Location: guard.php");
        exit();
    }
}

$todayJalaliFormatted = JalaliDate::format($today->year, $today->month, $today->day, 'd F Y');
$todayWeekday = JalaliDate::getWeekday($today->year, $today->month, $today->day);
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل نگهبان</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 12px;
            padding: 25px;
            position: relative;
        }

        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 2em;
            cursor: pointer;
            color: #555;
        }

        .extra-toggle {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .btn-report {
            display: block;
            margin: 30px auto;
            padding: 14px 40px;
            font-size: 1.2em;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-report:hover {
            background: #2980b9;
        }

        .report-status {
            text-align: center;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-size: 1.1em;
        }

        .status-good {
            background: #d4edda;
            color: #155724;
        }

        .status-warning {
            background: #fff3cd;
            color: #856404;
        }

        .status-bad {
            background: #f8d7da;
            color: #721c24;
        }

        .handover-section {
            margin-top: 25px;
            padding: 15px;
            background: #f0f8ff;
            border-radius: 8px;
            border-right: 4px solid #3498db;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="test-date">
            📅 امروز: <?php echo JalaliDate::format($today->year, $today->month, $today->day, 'l، d F Y'); ?>
            (میلادی: <?php echo date('Y-m-d l'); ?>)
        </div>

        <h1>سلام، <?php echo htmlspecialchars($_SESSION['name'] ?? 'نگهبان'); ?> عزیز</h1>

        <div class="date-display">
            📅 امروز: <span class="weekday-display"><?php echo $todayWeekday; ?></span> <?php echo $todayJalaliFormatted; ?>
        </div>

        <?php if ($active_shift): ?>
            <?php if ($hasExtraShift): ?>
                <div class="shift-info shift-extra">
                    ⚡ <strong>اضافه کاری فعال</strong><br>
                    این شیفت به عنوان اضافه کاری ثبت شده
                </div>
            <?php else: ?>
                <div class="shift-info shift-scheduled">
                    ✅ <strong>شیفت عادی فعال</strong>
                </div>
            <?php endif; ?>

            <div class="shift-info">
                <strong>زمان ورود:</strong><br>
                <?php echo DateTimeConverter::convertToJalaliDateTime($active_shift->getStartTime()); ?>
            </div>

            <form method="post">
                <button type="submit" name="end_shift" class="btn btn-end">پایان شیفت (خروج)</button>
            </form>

        <?php else: ?>

            <?php if ($isScheduledDay): ?>
                <div class="shift-info shift-scheduled">
                    ✅ <strong>امروز روز شیفت شماست</strong><br>
                    نوبت شیفت: شما
                </div>

                <form method="post">
                    <button type="submit" name="start_shift" class="btn btn-start">شروع شیفت (ورود)</button>
                </form>

            <?php else: ?>
                <div class="shift-info shift-not-scheduled">
                    ❌ <strong>امروز روز شیفت شما نیست</strong><br>
                    نوبت شیفت: <span class="guard-name"><?php echo $todayGuard ? htmlspecialchars($todayGuard->getName()) : 'نامشخص'; ?></span>
                </div>

                <form method="post">
                    <div class="extra-checkbox">
                        <input type="checkbox" name="is_extra" id="is_extra" value="1" required>
                        <label for="is_extra">
                            ✅ می‌خواهم به صورت اضافه کار وارد شوم
                        </label>
                    </div>
                    <button type="submit" name="start_shift" class="btn btn-start" style="background: #ff9800;">
                        ورود اضافه کار
                    </button>
                </form>
            <?php endif; ?>

        <?php endif; ?>

        <!-- دکمه ثبت گزارش -->
        <button id="openReportModalBtn" class="btn-report">
            ثبت گزارش
        </button>

        <!-- باکس وضعیت گزارش امروز -->
        <div class="report-status <?php
                                    if ($existingReport) {
                                        $checked = ($existingReport->appearance + $existingReport->vehicle_control + $existingReport->property_control +
                                            $existingReport->camera_monitoring + $existingReport->fire_safety + $existingReport->building_check +
                                            $existingReport->alarm_system + $existingReport->after_hours_entry + $existingReport->forbidden_entry +
                                            $existingReport->aquarium_feed + $existingReport->server_room_status + $existingReport->fingerprint +
                                            $existingReport->night_rounds);
                                        if ($checked >= 11) echo 'status-good';
                                        elseif ($checked >= 7) echo 'status-warning';
                                        else echo 'status-bad';
                                    } else {
                                        echo 'status-bad';
                                    }
                                    ?>">
            <?php echo $reportStatus; ?>
        </div>

        <a href="logout.php" class="logout-link">خروج از حساب کاربری</a>
    </div>

    <!-- مودال گزارش -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('reportModal').style.display='none'">×</span>

            <h2 style="text-align:center; color:#2c3e50;">
                ثبت / ویرایش گزارش شیفت
            </h2>

            <p style="text-align:center; color:#555; margin:15px 0;">
                <?php echo JalaliDate::format($today->year, $today->month, $today->day, 'l، d F Y'); ?>
            </p>

            <?php
            if (isset($_GET['msg'])) echo '<div class="success">' . htmlspecialchars($_GET['msg']) . '</div>';
            if (isset($_GET['err'])) echo '<div class="error">' . htmlspecialchars($_GET['err']) . '</div>';
            ?>

            <?php if (!$isOnDuty): ?>
                <div class="extra-toggle">
                    <label style="font-size:1.1em; display:flex; align-items:center; gap:12px; justify-content:center;">
                        <input type="checkbox" id="extraWorkCheck">
                        می‌خواهم به عنوان اضافه‌کار گزارش ثبت کنم
                    </label>
                </div>

                <div id="extraWorkSection" style="display:none; margin-top:25px;">
                    <form method="post">
                        <h4 style="margin-top:25px;">وضعیت‌های اصلی</h4>
                        <label><input type="checkbox" name="appearance"> آراستگی ظاهری و نظم</label>
                        <label><input type="checkbox" name="vehicle_control"> کنترل ورود/خروج خودروها و پارکینگ</label>
                        <label><input type="checkbox" name="property_control"> کنترل ورود/خروج اموال و تجهیزات</label>
                        <label><input type="checkbox" name="camera_monitoring"> نظارت بر دوربین‌های مداربسته</label>
                        <label><input type="checkbox" name="fire_safety"> بررسی اطفاء حریق، کپسول‌ها و آلارم‌ها</label>
                        <label><input type="checkbox" name="building_check"> چک روشنایی، پنجره‌ها، کرکره‌ها، سیستم گرمایشی/سرمایشی</label>
                        <label><input type="checkbox" name="alarm_system"> قطع/وصل سیستم ضدسرقت</label>
                        <label><input type="checkbox" name="after_hours_entry"> ثبت ورود/خروج خارج از ساعت اداری / تعطیلات</label>
                        <label><input type="checkbox" name="forbidden_entry"> عدم ورود افراد/خودروهای نظامی یا ممنوعه</label>
                        <label><input type="checkbox" name="aquarium_feed"> غذا دادن به ماهی‌های آکواریوم</label>
                        <label><input type="checkbox" name="server_room_status"> وضعیت اتاق سرور</label>
                        <label><input type="checkbox" name="fingerprint"> ثبت تردد با اثر انگشت</label>
                        <label><input type="checkbox" name="night_rounds"> بازدیدهای شبانه</label>

                        <label style="margin-top:25px;">حوادث / خرابی / آتش‌سوزی / قطعی:</label>
                        <textarea name="incidents_text" rows="3"></textarea>

                        <label>تماس با مدیریت / ۱۱۰ / ۱۲۵ و ... :</label>
                        <textarea name="contacts_text" rows="2"></textarea>

                        <label>یادداشت / موارد مشکوک / نیاز به پیگیری:</label>
                        <textarea name="notes_text" rows="4"></textarea>

                        <button type="submit" name="register_report" class="btn btn-start" style="margin-top:30px; width:100%; background:#27ae60;">
                            ثبت گزارش اضافه‌کار
                        </button>
                    </form>
                </div>

                <div id="noShiftMessage" style="text-align:center; padding:30px; background:#fff3cd; border-radius:8px; margin-top:20px;">
                    <h3 style="color:#856404;">شیفت رسمی امروز ندارید</h3>
                    <p>اگر اضافه‌کاری دارید، تیک بالا را بزنید.</p>
                </div>

            <?php else: ?>
                <form method="post">
                    <h4 style="margin-top:25px;">وضعیت‌های اصلی</h4>
                    <label><input type="checkbox" name="appearance" <?php echo ($existingReport->appearance ?? 1) ? 'checked' : ''; ?>> آراستگی ظاهری و نظم</label>
                    <label><input type="checkbox" name="vehicle_control" <?php echo ($existingReport->vehicle_control ?? 1) ? 'checked' : ''; ?>> کنترل ورود/خروج خودروها و پارکینگ</label>
                    <label><input type="checkbox" name="property_control" <?php echo ($existingReport->property_control ?? 1) ? 'checked' : ''; ?>> کنترل ورود/خروج اموال و تجهیزات</label>
                    <label><input type="checkbox" name="camera_monitoring" <?php echo ($existingReport->camera_monitoring ?? 1) ? 'checked' : ''; ?>> نظارت بر دوربین‌های مداربسته</label>
                    <label><input type="checkbox" name="fire_safety" <?php echo ($existingReport->fire_safety ?? 1) ? 'checked' : ''; ?>> بررسی اطفاء حریق، کپسول‌ها و آلارم‌ها</label>
                    <label><input type="checkbox" name="building_check" <?php echo ($existingReport->building_check ?? 1) ? 'checked' : ''; ?>> چک روشنایی، پنجره‌ها، کرکره‌ها، سیستم گرمایشی/سرمایشی</label>
                    <label><input type="checkbox" name="alarm_system" <?php echo ($existingReport->alarm_system ?? 1) ? 'checked' : ''; ?>> قطع/وصل سیستم ضدسرقت</label>
                    <label><input type="checkbox" name="after_hours_entry" <?php echo ($existingReport->after_hours_entry ?? 0) ? 'checked' : ''; ?>> ثبت ورود/خروج خارج از ساعت اداری / تعطیلات</label>
                    <label><input type="checkbox" name="forbidden_entry" <?php echo ($existingReport->forbidden_entry ?? 0) ? 'checked' : ''; ?>> عدم ورود افراد/خودروهای نظامی یا ممنوعه</label>
                    <label><input type="checkbox" name="aquarium_feed" <?php echo ($existingReport->aquarium_feed ?? 0) ? 'checked' : ''; ?>> غذا دادن به ماهی‌های آکواریوم</label>
                    <label><input type="checkbox" name="server_room_status" <?php echo ($existingReport->server_room_status ?? 1) ? 'checked' : ''; ?>> وضعیت اتاق سرور</label>
                    <label><input type="checkbox" name="fingerprint" <?php echo ($existingReport->fingerprint ?? 1) ? 'checked' : ''; ?>> ثبت تردد با اثر انگشت</label>
                    <label><input type="checkbox" name="night_rounds" <?php echo ($existingReport->night_rounds ?? 1) ? 'checked' : ''; ?>> بازدیدهای شبانه</label>

                    <!-- بخش تحویل شیفت -->
                    <div class="handover-section">
                        <label style="font-size:1.1em; display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" id="isHandover" name="is_handover">
                            <strong>شیفت به پایان رسیده و می‌خواهم تحویل دهم</strong>
                        </label>

                        <div id="handoverFields" style="display:none; margin-top:20px; padding:15px; background:#fff; border-radius:8px;">
                            <label>ساعت تحویل پست:</label>
                            <input type="time" name="handover_time" value="<?php echo htmlspecialchars($existingReport->handover_time ?? ''); ?>" style="width:100%; padding:8px; margin:10px 0;">

                            <label style="margin-top:15px;">تحویل به:</label>
                            <input type="text" readonly value="<?php echo htmlspecialchars($nextGuardName); ?>" style="width:100%; padding:8px; margin:10px 0; background:#f5f5f5;">
                            <input type="hidden" name="previous_guard_id" value="<?php echo $nextGuardId ?: ''; ?>">

                            <p style="color:#7f8c8d; font-size:0.9em; margin-top:10px;">
                                ⚠️ توجه: پس از تایید تحویل شیفت، گزارش نهایی ثبت می‌شود.
                            </p>
                        </div>
                    </div>

                    <label style="margin-top:25px;">حوادث / خرابی / آتش‌سوزی / قطعی:</label>
                    <textarea name="incidents_text" rows="3"><?php echo htmlspecialchars($existingReport->incidents_text ?? ''); ?></textarea>

                    <label>تماس با مدیریت / ۱۱۰ / ۱۲۵ و ... :</label>
                    <textarea name="contacts_text" rows="2"><?php echo htmlspecialchars($existingReport->contacts_text ?? ''); ?></textarea>

                    <label>یادداشت / موارد مشکوک / نیاز به پیگیری:</label>
                    <textarea name="notes_text" rows="4"><?php echo htmlspecialchars($existingReport->notes_text ?? ''); ?></textarea>

                    <button type="submit" name="register_report" class="btn btn-start" style="margin-top:30px; width:100%; background:#27ae60;">
                        <?php echo $existingReport ? 'به‌روزرسانی گزارش' : 'ثبت گزارش'; ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // باز کردن مودال
        document.getElementById('openReportModalBtn').onclick = function() {
            document.getElementById('reportModal').style.display = 'flex';
        };

        // بررسی اضافه کاری
        var extraWorkCheck = document.getElementById('extraWorkCheck');
        if (extraWorkCheck) {
            extraWorkCheck.addEventListener('change', function() {
                var extraSection = document.getElementById('extraWorkSection');
                var noShiftMsg = document.getElementById('noShiftMessage');

                if (extraSection) {
                    extraSection.style.display = this.checked ? 'block' : 'none';
                }

                if (noShiftMsg) {
                    noShiftMsg.style.display = this.checked ? 'none' : 'block';
                }
            });
        }

        // بررسی تحویل شیفت
        var isHandover = document.getElementById('isHandover');
        if (isHandover) {
            isHandover.addEventListener('change', function() {
                var handoverFields = document.getElementById('handoverFields');
                if (handoverFields) {
                    handoverFields.style.display = this.checked ? 'block' : 'none';
                }
            });
        }
    </script>
</body>

</html>