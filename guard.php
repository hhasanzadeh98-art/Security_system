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

$isScheduledDay = false;
$todayGuard = null;
$guardCount = count($guards);
if ($guardCount > 0) {
    $dayOfYear = JalaliDate::getDayOfYear($today->year, $today->month, $today->day);
    $guardIndex = ($dayOfYear - 1) % $guardCount;
    $scheduledGuardId = $guards[$guardIndex]->getId();
    $todayGuard = $guards[$guardIndex];
    $isScheduledDay = ($scheduledGuardId == $user_id);
}

$canReport = $isOnDuty || $isScheduledDay;

// روز هفته برای غذا ماهی
$weekday = JalaliDate::getWeekday($today->year, $today->month, $today->day);
$isFridayOrThursday = in_array($weekday, ['پنج‌شنبه', 'جمعه']);

// گزارش امروز
$reportModel = new GuardShiftReport();
$existingReport = $canReport ? $reportModel->getByDateAndGuard($todayReportDate, $currentUser->getId()) : null;

// پردازش فرم گزارش - فقط اگر شیفت فعال باشد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $active_shift) {
    
    // جمع‌آوری داده‌های ورود/خروج افراد (از فیلد مخفی)
    $peopleEntries = [];
    if (!empty($_POST['people_entries_data'])) {
        $peopleEntries = json_decode($_POST['people_entries_data'], true);
        if (!is_array($peopleEntries)) {
            $peopleEntries = [];
        }
    }

    // جمع‌آوری داده‌های ورود/خروج خودرو (میتوانید چندتا داشته باشید)
    $vehicleEntries = [];
    if (!empty($_POST['vehicle_plate_type']) && !empty($_POST['vehicle_car_type'])) {
        $vehicleEntries[] = [
            'plate_type' => $_POST['vehicle_plate_type'],
            'car_type' => $_POST['vehicle_car_type'],
            'note' => $_POST['vehicle_note'] ?? null,
            'time' => $_POST['vehicle_entry_time'] ?? date('H:i:s')
        ];
    }

    // جمع‌آوری داده‌های ورود/خروج اموال
    $propertyEntries = [];
    if (!empty($_POST['property_type'])) {
        $propertyEntries[] = [
            'type' => $_POST['property_type'],
            'has_number' => isset($_POST['property_has_number']) ? 1 : 0,
            'number' => $_POST['property_number'] ?? null,
            'time' => $_POST['property_entry_time'] ?? date('H:i:s')
        ];
    }

    // وضعیت روشنایی
    $lightingChecked = isset($_POST['lighting_check']) ? 1 : 0;
    $lightingComputer = isset($_POST['lighting_computer']) ? 1 : 0;
    $lightingLamp = isset($_POST['lighting_lamp']) ? 1 : 0;
    $lightingSocket = isset($_POST['lighting_socket']) ? 1 : 0;
    $lightingOther = $_POST['lighting_other'] ?? null;

    // وضعیت حفاظتی
    $protectiveChecked = isset($_POST['protective_check']) ? 1 : 0;
    $protectiveShutter = isset($_POST['protective_shutter']) ? 1 : 0;
    $protectiveAlarm = isset($_POST['protective_alarm']) ? 1 : 0;
    $protectiveLock = isset($_POST['protective_lock']) ? 1 : 0;
    $protectiveOther = $_POST['protective_other'] ?? null;

    // وضعیت سرمایشی/گرمایشی
    $coolingHeatingChecked = isset($_POST['cooling_heating_check']) ? 1 : 0;
    $coolingKooler = isset($_POST['cooling_kooler']) ? 1 : 0;
    $heatingHeater = isset($_POST['heating_heater']) ? 1 : 0;
    $coolingHeatingOther = $_POST['cooling_heating_other'] ?? null;

    // بررسی بخاری و گاز
    $heaterGasChecked = isset($_POST['heater_gas_check']) ? 1 : 0;
    $heaterOff = isset($_POST['heater_off']) ? 1 : 0;
    $gasLeakCheck = isset($_POST['gas_leak_check']) ? 1 : 0;
    $gasValveClosed = isset($_POST['gas_valve_closed']) ? 1 : 0;
    $heaterGasNote = $_POST['heater_gas_note'] ?? null;

    // وضعیت دوربین
    $cameraStatus = $_POST['camera_overall_status'] ?? null;
    $cameraDetails = $_POST['camera_details'] ?? null;

    // گزارش‌های مهم
    $importantIncident = isset($_POST['important_incident']) ? 1 : 0;
    $importantFailure = isset($_POST['important_failure']) ? 1 : 0;
    $importantSuspicious = isset($_POST['important_suspicious']) ? 1 : 0;
    $importantFollowup = isset($_POST['important_followup']) ? 1 : 0;
    $importantOther = $_POST['important_other'] ?? null;

    $data = [
        'report_date' => $todayReportDate,
        'jalali_year' => $today->year,
        'jalali_month' => $today->month,
        'jalali_day' => $today->day,
        'guard_id' => $currentUser->getId(),
        'shift_type' => $_POST['shift_type'] ?? '24h',
        'handover_time' => $_POST['handover_time'] ?? null,
        'previous_guard_id' => $_POST['previous_guard_id'] ?? null,

        // چک‌باکس‌های اصلی
        'appearance' => isset($_POST['appearance']) ? 1 : 0,
        'vehicle_control' => isset($_POST['vehicle_control']) ? 1 : 0,
        'property_control' => isset($_POST['property_control']) ? 1 : 0,
        'camera_monitoring' => isset($_POST['camera_monitoring']) ? 1 : 0,
        'fire_safety' => isset($_POST['fire_safety']) ? 1 : 0,
        'building_check' => isset($_POST['building_check']) ? 1 : 0,
        'alarm_system' => isset($_POST['alarm_system']) ? 1 : 0,
        'after_hours_entry' => isset($_POST['after_hours_entry']) ? 1 : 0,
        'forbidden_entry' => isset($_POST['forbidden_entry']) ? 1 : 0,
        'aquarium_feed' => ($isFridayOrThursday && isset($_POST['aquarium_feed'])) ? 1 : 0,
        'server_room_status' => isset($_POST['server_room_status']) ? 1 : 0,
        'fingerprint' => isset($_POST['fingerprint']) ? 1 : 0,
        'night_rounds' => isset($_POST['night_rounds']) ? 1 : 0,

        // داده‌های ورود/خروج
        'people_entries' => $peopleEntries,
        'vehicle_entries' => $vehicleEntries,
        'property_entries' => $propertyEntries,

        // وضعیت‌ها
        'lighting_checked' => $lightingChecked,
        'lighting_computer' => $lightingComputer,
        'lighting_lamp' => $lightingLamp,
        'lighting_socket' => $lightingSocket,
        'lighting_other' => $lightingOther,

        'protective_checked' => $protectiveChecked,
        'protective_shutter' => $protectiveShutter,
        'protective_alarm' => $protectiveAlarm,
        'protective_lock' => $protectiveLock,
        'protective_other' => $protectiveOther,

        'cooling_heating_checked' => $coolingHeatingChecked,
        'cooling_kooler' => $coolingKooler,
        'heating_heater' => $heatingHeater,
        'cooling_heating_other' => $coolingHeatingOther,

        'heater_gas_checked' => $heaterGasChecked,
        'heater_off' => $heaterOff,
        'gas_leak_check' => $gasLeakCheck,
        'gas_valve_closed' => $gasValveClosed,
        'heater_gas_note' => $heaterGasNote,

        'camera_status' => $cameraStatus,
        'camera_details' => $cameraDetails,

        'night_round1_time' => $_POST['night_round1_time'] ?? null,
        'night_round2_time' => $_POST['night_round2_time'] ?? null,
        'night_rounds_note' => trim($_POST['night_rounds_note'] ?? ''),
        
        'server_temp_status' => $_POST['server_temp_status'] ?? 'normal',
        'ups_status' => $_POST['ups_status'] ?? 'healthy',
        'fire_alarm_status' => $_POST['fire_alarm_status'] ?? 'active',
        'server_room_note' => trim($_POST['server_room_note'] ?? ''),
        
        'tasks_performed' => trim($_POST['tasks_performed'] ?? ''),

        'important_incident' => $importantIncident,
        'important_failure' => $importantFailure,
        'important_suspicious' => $importantSuspicious,
        'important_followup' => $importantFollowup,
        'important_other' => $importantOther,

        'incidents_text' => trim($_POST['incidents_text'] ?? ''),
        'contacts_text' => trim($_POST['contacts_text'] ?? ''),
        'notes_text' => trim($_POST['notes_text'] ?? ''),

        'handover_signature' => trim($_POST['handover_signature'] ?? $currentUser->getName()),
        'received_signature' => trim($_POST['received_signature'] ?? ''),
    ];

    if (isset($_POST['register_report']) || isset($_POST['edit_report'])) {
        if ($existingReport) {
            $data['guard_id'] = $existingReport->guard_id;
            if ($reportModel->update($existingReport->id, $data)) {
                header("Location: guard.php?msg=" . urlencode("گزارش با موفقیت ویرایش شد"));
                exit();
            } else {
                header("Location: guard.php?err=" . urlencode("خطا در ویرایش گزارش"));
                exit();
            }
        } else {
            $newId = $reportModel->create($data);
            if ($newId !== false && $newId > 0) {
                header("Location: guard.php?msg=" . urlencode("گزارش با موفقیت ثبت شد"));
                exit();
            } else {
                header("Location: guard.php?err=" . urlencode("خطا در ثبت گزارش"));
                exit();
            }
        }
    }

    // حذف گزارش
    if (isset($_POST['delete_report']) && $existingReport) {
        if ($reportModel->delete($existingReport->id, $currentUser->getId())) {
            header("Location: guard.php?msg=" . urlencode("گزارش با موفقیت حذف شد"));
            exit();
        } else {
            header("Location: guard.php?err=" . urlencode("خطا در حذف گزارش"));
            exit();
        }
    }
}

// بقیه کد شیفت و ورود/خروج
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

// دریافت لیست پرسنل
$personnelModel = new Personnel();
$allPersonnel = $personnelModel->getAllPersonnel();
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل نگهبان</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .report-form {
            margin-top: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .conditional-section {
            display: none;
            margin-top: 15px;
            padding: 15px;
            background: #fff;
            border: 1px dashed #ccc;
            border-radius: 6px;
        }

        .btn-report {
            padding: 12px 40px;
            font-size: 1.1em;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 10px;
        }

        .btn-edit {
            background: #f39c12;
        }

        .btn-delete {
            background: #e74c3c;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        
        .section-title {
            background: #34495e;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-top: 25px;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .no-shift-message {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin: 30px 0;
        }
        
        .no-shift-message .icon {
            font-size: 5em;
            margin-bottom: 20px;
        }
        
        .no-shift-message h3 {
            color: #856404;
            font-size: 1.8em;
            margin-bottom: 15px;
        }
        
        .no-shift-message p {
            color: #856404;
            font-size: 1.2em;
            margin-bottom: 20px;
        }
        
        .no-shift-message .guide-box {
            background: #ffe0b2;
            padding: 20px;
            border-radius: 10px;
            display: inline-block;
            margin-top: 15px;
        }
        
        .entry-item {
            background: white;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-right: 3px solid #1976d2;
        }
        
        .remove-btn {
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 2px 8px;
            cursor: pointer;
        }
        
        .personnel-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            margin-left: 5px;
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

        <!-- بخش گزارش‌دهی - فقط در صورت وجود شیفت فعال -->
        <?php if ($active_shift): ?>
            <div class="report-form">
                <h3 style="text-align:center; margin-bottom:20px;">📝 گزارش شیفت جاری</h3>
                
                <div class="shift-info shift-scheduled" style="background: #e3f2fd; border-right-color: #1976d2; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 2em;">✅</span>
                        <div>
                            <strong>شما یک شیفت فعال دارید.</strong><br>
                            لطفاً گزارش عملکرد خود را در این شیفت ثبت کنید.
                        </div>
                    </div>
                </div>
                
                <?php
                if (isset($_GET['msg'])) echo '<div class="success">' . htmlspecialchars($_GET['msg']) . '</div>';
                if (isset($_GET['err'])) echo '<div class="error">' . htmlspecialchars($_GET['err']) . '</div>';
                ?>

                <form method="post" id="reportForm">
                    <!-- نوع شیفت -->
                    <div class="section-title">نوع شیفت</div>
                    <select name="shift_type" style="width:100%; padding:10px; margin-bottom:15px;">
                        <option value="24h" <?php echo ($existingReport->shift_type ?? '24h') == '24h' ? 'selected' : ''; ?>>۲۴ ساعته</option>
                        <option value="morning" <?php echo ($existingReport->shift_type ?? '') == 'morning' ? 'selected' : ''; ?>>صبح تا عصر</option>
                        <option value="evening" <?php echo ($existingReport->shift_type ?? '') == 'evening' ? 'selected' : ''; ?>>عصر تا صبح</option>
                    </select>

                    <!-- چک‌باکس‌های اصلی (13 مورد) -->
                    <div class="section-title">وضعیت‌های اصلی (13 مورد)</div>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="appearance" <?php echo ($existingReport->appearance ?? 1) ? 'checked' : ''; ?>> وضعیت ظاهری</label>
                        <label><input type="checkbox" name="vehicle_control" <?php echo ($existingReport->vehicle_control ?? 1) ? 'checked' : ''; ?>> کنترل خودرو</label>
                        <label><input type="checkbox" name="property_control" <?php echo ($existingReport->property_control ?? 1) ? 'checked' : ''; ?>> کنترل اموال</label>
                        <label><input type="checkbox" name="camera_monitoring" <?php echo ($existingReport->camera_monitoring ?? 1) ? 'checked' : ''; ?>> نظارت دوربین</label>
                        <label><input type="checkbox" name="fire_safety" <?php echo ($existingReport->fire_safety ?? 1) ? 'checked' : ''; ?>> ایمنی آتش</label>
                        <label><input type="checkbox" name="building_check" <?php echo ($existingReport->building_check ?? 1) ? 'checked' : ''; ?>> بررسی ساختمان</label>
                        <label><input type="checkbox" name="alarm_system" <?php echo ($existingReport->alarm_system ?? 1) ? 'checked' : ''; ?>> سیستم دزدگیر</label>
                        <label><input type="checkbox" name="after_hours_entry" <?php echo ($existingReport->after_hours_entry ?? 0) ? 'checked' : ''; ?>> ورود بعد از ساعت</label>
                        <label><input type="checkbox" name="forbidden_entry" <?php echo ($existingReport->forbidden_entry ?? 0) ? 'checked' : ''; ?>> ورود ممنوعه</label>
                        <label><input type="checkbox" name="aquarium_feed" <?php echo ($isFridayOrThursday && ($existingReport->aquarium_feed ?? 0)) ? 'checked' : ''; ?> <?php echo !$isFridayOrThursday ? 'disabled' : ''; ?>> غذا دهی آکواریوم</label>
                        <label><input type="checkbox" name="server_room_status" <?php echo ($existingReport->server_room_status ?? 1) ? 'checked' : ''; ?>> وضعیت اتاق سرور</label>
                        <label><input type="checkbox" name="fingerprint" <?php echo ($existingReport->fingerprint ?? 1) ? 'checked' : ''; ?>> اثر انگشت</label>
                        <label><input type="checkbox" name="night_rounds" <?php echo ($existingReport->night_rounds ?? 1) ? 'checked' : ''; ?>> گشت شبانه</label>
                    </div>

                    <!-- ========== بخش ورود/خروج افراد ========== -->
                    <div class="section-title">ورود و خروج افراد</div>
                    
                    <div style="background: #f0f7ff; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h4 style="color: #1976d2; margin-bottom: 15px;">➕ افزودن ورود/خروج جدید</h4>
                        
                        <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="people_type" value="personnel" class="people-type-radio" checked> 
                                <span>پرسنل (کارمندان)</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="people_type" value="guest" class="people-type-radio"> 
                                <span>مهمان / مراجعه‌کننده</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="radio" name="people_type" value="other" class="people-type-radio"> 
                                <span>سایر</span>
                            </label>
                        </div>
                        
                        <!-- بخش انتخاب پرسنل -->
                        <div id="personnelSelection">
                            <label>انتخاب پرسنل (چند نفره):</label>
                            <select name="personnel_ids[]" multiple size="5" style="width:100%; padding:8px; margin-top:5px;">
                                <?php foreach ($allPersonnel as $person): ?>
                                    <option value="<?php echo $person->id; ?>">
                                        <?php echo htmlspecialchars($person->full_name); ?> 
                                        (<?php echo htmlspecialchars($person->position ?? 'بدون سمت'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display: block; margin-top:5px; color:#666;">برای انتخاب چند نفر Ctrl را نگه دارید</small>
                        </div>
                        
                        <!-- بخش ورود مهمان -->
                        <div id="guestSelection" style="display: none;">
                            <label>نام و نام خانوادگی مهمان:</label>
                            <input type="text" name="guest_name" placeholder="مثال: علی محمدی" style="width:100%; padding:8px; margin-top:5px;">
                            
                            <label style="margin-top: 10px;">شماره تماس (اختیاری):</label>
                            <input type="text" name="guest_phone" placeholder="مثال: 09121234567" style="width:100%; padding:8px; margin-top:5px;">
                            
                            <label style="margin-top: 10px;">محل مراجعه:</label>
                            <input type="text" name="guest_destination" placeholder="مثال: واحد مالی" style="width:100%; padding:8px; margin-top:5px;">
                        </div>
                        
                        <!-- بخش سایر -->
                        <div id="otherSelection" style="display: none;">
                            <label>توضیحات:</label>
                            <input type="text" name="other_people_desc" placeholder="مثال: پیک موتوری، خدمه، ..." style="width:100%; padding:8px; margin-top:5px;">
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <label>ساعت ورود/خروج:</label>
                            <input type="time" name="people_entry_time" value="<?php echo date('H:i'); ?>" style="width:100%; padding:8px; margin-top:5px;">
                        </div>
                        
                        <div style="margin-top: 15px; text-align: left;">
                            <button type="button" id="addPeopleEntry" class="btn-primary" style="background: #1976d2; color: white; border: none; padding: 8px 20px; border-radius: 4px; cursor: pointer;">➕ افزودن به لیست</button>
                        </div>
                    </div>
                    
                    <!-- لیست ورود/خروج‌های ثبت شده -->
                    <div id="peopleEntriesList" style="margin-bottom: 20px;">
                        <h4 style="color: #2c3e50; margin-bottom: 10px;">لیست ورود/خروج‌های ثبت شده:</h4>
                        <div id="entriesContainer" style="background: #f5f5f5; padding: 10px; border-radius: 5px; min-height: 50px;">
                            <div style="color: #999; text-align: center; padding: 20px;">هیچ ورودی ثبت نشده</div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="people_entries_data" id="peopleEntriesData" value="">

                    <!-- ========== ورود و خروج خودرو ========== -->
                    <div class="section-title">ورود و خروج خودرو</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label>نوع پلاک:</label>
                        <select name="vehicle_plate_type" style="width:100%; padding:8px; margin:5px 0 10px;">
                            <option value="">انتخاب کنید</option>
                            <option value="personal">شخصی</option>
                            <option value="governmental">دولتی</option>
                            <option value="military">نظامی</option>
                            <option value="other">سایر</option>
                        </select>

                        <label>نوع ماشین:</label>
                        <input type="text" name="vehicle_car_type" placeholder="مثال: پراید، پژو، ..." style="width:100%; padding:8px; margin:5px 0 10px;">

                        <label>توضیحات (اختیاری):</label>
                        <textarea name="vehicle_note" rows="2" style="width:100%; padding:8px; margin:5px 0 10px;"></textarea>

                        <label>ساعت ورود/خروج:</label>
                        <input type="time" name="vehicle_entry_time" value="<?php echo date('H:i'); ?>" style="width:100%; padding:8px; margin:5px 0;">
                    </div>

                    <!-- ========== ورود و خروج اموال ========== -->
                    <div class="section-title">ورود و خروج اموال</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label>نوع اموال:</label>
                        <input type="text" name="property_type" placeholder="مثال: لپ‌تاپ، مانیتور، ..." style="width:100%; padding:8px; margin:5px 0 10px;">
                        
                        <label style="display: flex; align-items: center; gap: 5px; margin:10px 0;">
                            <input type="checkbox" name="property_has_number" id="propertyHasNumber"> 
                            دارای شماره اموال
                        </label>
                        
                        <div id="propertyNumberField" style="display: none; margin-top:10px;">
                            <label>شماره اموال:</label>
                            <input type="text" name="property_number" placeholder="شماره اموال" style="width:100%; padding:8px;">
                        </div>
                        
                        <label style="margin-top: 15px;">ساعت ورود/خروج:</label>
                        <input type="time" name="property_entry_time" value="<?php echo date('H:i'); ?>" style="width:100%; padding:8px; margin:5px 0;">
                    </div>

                    <!-- ========== وضعیت روشنایی ========== -->
                    <div class="section-title">سیستم روشنایی</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 15px;">
                            <input type="checkbox" name="lighting_check" id="lightingCheck"> 
                            سیستم روشنایی چک شده
                        </label>

                        <div id="lightingDetails" style="display: none; margin-top:15px;">
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="lighting_computer"> کامپیوتر / مانیتور روشن مونده</label>
                                <label><input type="checkbox" name="lighting_lamp"> چراغ طبقه / سالن روشن مونده</label>
                                <label><input type="checkbox" name="lighting_socket"> سه راهی یا پریز روشن مونده</label>
                            </div>
                            <label style="margin-top:10px;">سایر:</label>
                            <input type="text" name="lighting_other" style="width:100%; padding:8px;">
                        </div>
                    </div>

                    <!-- ========== وضعیت حفاظتی ========== -->
                    <div class="section-title">سیستم حفاظتی</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 15px;">
                            <input type="checkbox" name="protective_check" id="protectiveCheck"> 
                            سیستم حفاظتی چک شده
                        </label>

                        <div id="protectiveDetails" style="display: none; margin-top:15px;">
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="protective_shutter"> کرکره اتاق‌ها / پذیرایی کشیده نشده</label>
                                <label><input type="checkbox" name="protective_alarm"> سیستم ضد سرقت فعال نشده</label>
                                <label><input type="checkbox" name="protective_lock"> قفل درب طبقات / حفاظ کرکره‌ای باز مونده</label>
                            </div>
                            <label style="margin-top:10px;">سایر:</label>
                            <input type="text" name="protective_other" style="width:100%; padding:8px;">
                        </div>
                    </div>

                    <!-- ========== وضعیت سرمایشی/گرمایشی ========== -->
                    <div class="section-title">سیستم سرمایشی / گرمایشی</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 15px;">
                            <input type="checkbox" name="cooling_heating_check" id="coolingHeatingCheck"> 
                            سیستم سرمایشی/گرمایشی چک شده
                        </label>

                        <div id="coolingHeatingDetails" style="display: none; margin-top:15px;">
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="cooling_kooler"> کولر / پنکه روشن مونده</label>
                                <label><input type="checkbox" name="heating_heater"> بخاری / سماور روشن مونده</label>
                            </div>
                            <label style="margin-top:10px;">سایر:</label>
                            <input type="text" name="cooling_heating_other" style="width:100%; padding:8px;">
                        </div>
                    </div>

                    <!-- ========== بررسی بخاری و گاز ========== -->
                    <div class="section-title">بررسی بخاری و گاز</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 5px; margin-bottom: 15px;">
                            <input type="checkbox" name="heater_gas_check" id="heaterGasCheck"> 
                            بررسی بخاری و گاز انجام شده
                        </label>

                        <div id="heaterGasDetails" style="display: none; margin-top:15px;">
                            <div class="checkbox-group">
                                <label><input type="checkbox" name="heater_off"> بخاری خاموش شده / ایمنی چک شده</label>
                                <label><input type="checkbox" name="gas_leak_check"> بوی گاز یا نشتی چک شده</label>
                                <label><input type="checkbox" name="gas_valve_closed"> شیر گاز بسته شده</label>
                            </div>
                            <label style="margin-top:10px;">توضیحات اضافی:</label>
                            <textarea name="heater_gas_note" rows="2" style="width:100%; padding:8px;"></textarea>
                        </div>
                    </div>

                    <!-- ========== نظارت تصویری ========== -->
                    <div class="section-title">نظارت تصویری - دوربین‌ها</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label>وضعیت دوربین‌ها:</label>
                        <select name="camera_overall_status" style="width:100%; padding:8px; margin-bottom:10px;">
                            <option value="healthy">سالم</option>
                            <option value="disconnected">قطعی</option>
                            <option value="dirty">کثیف</option>
                            <option value="broken">شکسته</option>
                            <option value="malfunction">مشکل فنی / تصویر ندارد</option>
                        </select>

                        <label>توضیحات دوربین‌ها (اختیاری):</label>
                        <textarea name="camera_details" rows="3" style="width:100%; padding:8px;"></textarea>
                    </div>

                    <!-- ========== بازدید شبانه ========== -->
                    <div class="section-title">بازدید شبانه</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label>نوبت اول (ساعت):</label>
                        <input type="time" name="night_round1_time" style="width:100%; padding:8px; margin-bottom:10px;">

                        <label>نوبت دوم (ساعت):</label>
                        <input type="time" name="night_round2_time" style="width:100%; padding:8px; margin-bottom:10px;">

                        <label>توضیحات بازدید شبانه:</label>
                        <textarea name="night_rounds_note" rows="3" style="width:100%; padding:8px;"></textarea>
                    </div>

                    <!-- ========== اتاق سرور ========== -->
                    <div class="section-title">بررسی اتاق سرور</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label>دما اتاق سرور:</label>
                        <select name="server_temp_status" style="width:100%; padding:8px; margin-bottom:10px;">
                            <option value="normal">نرمال</option>
                            <option value="high">بالا</option>
                            <option value="low">پایین</option>
                        </select>

                        <label>وضعیت UPS:</label>
                        <select name="ups_status" style="width:100%; padding:8px; margin-bottom:10px;">
                            <option value="healthy">سالم</option>
                            <option value="problem">مشکل</option>
                        </select>

                        <label>اعلام حریق:</label>
                        <select name="fire_alarm_status" style="width:100%; padding:8px; margin-bottom:10px;">
                            <option value="active">فعال</option>
                            <option value="inactive">غیرفعال</option>
                            <option value="problem">مشکل</option>
                        </select>

                        <label>توضیحات اتاق سرور:</label>
                        <textarea name="server_room_note" rows="3" style="width:100%; padding:8px;"></textarea>
                    </div>

                    <!-- ========== کارهای انجام شده ========== -->
                    <div class="section-title">کارهای انجام‌شده در شیفت</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <textarea name="tasks_performed" rows="5" style="width:100%; padding:8px;" placeholder="کارهای انجام‌شده، اقدامات، بازدیدها و ... را بنویسید"></textarea>
                    </div>

                    <!-- ========== گزارش‌های مهم ========== -->
                    <div class="section-title">گزارش‌های مهم</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="important_incident" value="1"> حوادث / آتش‌سوزی</label>
                            <label><input type="checkbox" name="important_failure" value="1"> خرابی / قطعی</label>
                            <label><input type="checkbox" name="important_suspicious" value="1"> موارد مشکوک</label>
                            <label><input type="checkbox" name="important_followup" value="1"> نیاز به بررسی و پیگیری</label>
                        </div>
                        <label style="margin-top:15px;">سایر گزارش مهم:</label>
                        <input type="text" name="important_other" placeholder="توضیحات سایر موارد مهم" style="width:100%; padding:8px;">
                    </div>

                    <!-- ========== متون اصلی ========== -->
                    <div class="section-title">گزارش‌های تکمیلی</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label>حوادث و اتفاقات:</label>
                        <textarea name="incidents_text" rows="3" style="width:100%; padding:8px; margin-bottom:10px;"></textarea>

                        <label>تماس‌ها:</label>
                        <textarea name="contacts_text" rows="3" style="width:100%; padding:8px; margin-bottom:10px;"></textarea>

                        <label>یادداشت‌ها:</label>
                        <textarea name="notes_text" rows="3" style="width:100%; padding:8px;"></textarea>
                    </div>

                    <!-- ========== امضاها ========== -->
                    <div class="section-title">امضاها</div>
                    
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <label>امضا تحویل‌دهنده:</label>
                        <input type="text" name="handover_signature" value="<?php echo htmlspecialchars($currentUser->getName()); ?>" style="width:100%; padding:8px; margin-bottom:10px;">

                        <label>امضا تحویل‌گیرنده:</label>
                        <input type="text" name="received_signature" style="width:100%; padding:8px;">
                    </div>

                    <!-- ========== تیک پایان شیفت ========== -->
                    <div style="margin:20px 0; padding:15px; background:#f8f9fa; border-radius:8px;">
                        <label style="font-weight:bold; display:flex; align-items:center; gap:10px;">
                            <input type="checkbox" id="isHandover" name="is_handover">
                            شیفت به پایان رسیده و تحویل داده شده است
                        </label>

                        <div id="handoverFields" style="margin-top:15px; display:none;">
                            <label>ساعت تحویل پست:</label>
                            <input type="time" name="handover_time" style="width:100%; padding:10px;">

                            <?php
                            // محاسبه نگهبان بعدی
                            $tomorrowJalali = JalaliDate::gregorianToJalali(
                                date('Y', strtotime('+1 day')),
                                date('m', strtotime('+1 day')),
                                date('d', strtotime('+1 day'))
                            );
                            $nextGuardId = $scheduleModel->getGuardForDate($tomorrowJalali->year, $tomorrowJalali->month, $tomorrowJalali->day);
                            $nextGuardName = $nextGuardId ? $scheduleModel->getGuardName($nextGuardId) : 'نامشخص (شیفت بعدی تنظیم نشده)';
                            ?>

                            <label style="margin-top:15px;">تحویل به (نگهبان بعدی):</label>
                            <input type="text" readonly value="<?php echo htmlspecialchars($nextGuardName); ?>" style="width:100%; padding:10px; background:#eee; border:1px solid #ccc;">
                            <input type="hidden" name="previous_guard_id" value="<?php echo $nextGuardId ?: ''; ?>">
                        </div>
                    </div>

                    <!-- دکمه‌های ثبت -->
                    <div style="margin-top:40px; text-align:center;">
                        <?php if (!$existingReport): ?>
                            <button type="submit" name="register_report" class="btn-report" style="background:#27ae60;">
                                ثبت گزارش جدید
                            </button>
                        <?php endif; ?>

                        <?php if ($existingReport): ?>
                            <button type="submit" name="edit_report" class="btn-report btn-edit">
                                ویرایش گزارش
                            </button>
                            <button type="submit" name="delete_report" class="btn-report btn-delete" onclick="return confirm('مطمئن هستید می‌خواهید کل گزارش حذف شود؟');">
                                حذف گزارش
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- پیام برای وقتی شیفت فعال نیست -->
            <div class="no-shift-message">
                <div class="icon">⏰</div>
                <h3>شما هنوز شیفت خود را شروع نکرده‌اید!</h3>
                <p>
                    برای ثبت گزارش شیفت، ابتدا باید دکمه "شروع شیفت" را بزنید.
                </p>
                
                <?php if ($isScheduledDay): ?>
                    <div class="guide-box">
                        <strong>👉 امروز روز شیفت شماست.</strong><br>
                        از دکمه سبز رنگ "شروع شیفت" در بالا استفاده کنید.
                    </div>
                <?php elseif ($canReport): ?>
                    <div class="guide-box">
                        <strong>👉 شما می‌توانید به صورت اضافه کار شروع کنید.</strong><br>
                        گزینه "می‌خواهم به صورت اضافه کار وارد شوم" را فعال کرده و شروع کنید.
                    </div>
                <?php else: ?>
                    <div class="guide-box">
                        <strong>👉 امروز روز شیفت شما نیست.</strong><br>
                        برای شروع اضافه کار، گزینه مربوطه را فعال کنید.
                    </div>
                <?php endif; ?>
            </div>

            <!-- اگر قبلاً گزارش ثبت کرده -->
            <?php if ($existingReport): ?>
                <div style="background: #f0f0f0; padding: 20px; border-radius: 8px; margin-top: 20px;">
                    <h4 style="color: #666;">📋 گزارش قبلی شما برای امروز</h4>
                    <p style="color: #666;">
                        شما قبلاً برای تاریخ <?php echo JalaliDate::format($today->year, $today->month, $today->day, 'd F Y'); ?> گزارش ثبت کرده‌اید.
                    </p>
                    <div style="color: #999; font-size: 0.9em;">
                        📅 تاریخ ثبت: <?php echo $todayReportDate; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="logout.php" class="logout-link">خروج از حساب کاربری</a>
        </div>
    </div>

    <script>
        // ========== مدیریت ورود/خروج افراد ==========
        let peopleEntries = [];

        // نمایش/مخفی کردن بخش‌ها بر اساس نوع انتخاب
        document.querySelectorAll('.people-type-radio').forEach(radio => {
            radio.addEventListener('change', function() {
                const value = this.value;
                document.getElementById('personnelSelection').style.display = value === 'personnel' ? 'block' : 'none';
                document.getElementById('guestSelection').style.display = value === 'guest' ? 'block' : 'none';
                document.getElementById('otherSelection').style.display = value === 'other' ? 'block' : 'none';
            });
        });

        // افزودن به لیست
        document.getElementById('addPeopleEntry').addEventListener('click', function() {
            const selectedType = document.querySelector('input[name="people_type"]:checked').value;
            const entryTime = document.querySelector('input[name="people_entry_time"]').value;
            
            let entryText = '';
            let entryData = {
                type: selectedType,
                time: entryTime,
                displayText: ''
            };
            
            if (selectedType === 'personnel') {
                const select = document.querySelector('select[name="personnel_ids[]"]');
                const selectedOptions = Array.from(select.selectedOptions).map(opt => ({
                    id: opt.value,
                    name: opt.text.split(' (')[0]
                }));
                
                if (selectedOptions.length === 0) {
                    alert('حداقل یک نفر را انتخاب کنید');
                    return;
                }
                
                const names = selectedOptions.map(opt => opt.name).join('، ');
                entryText = `👥 پرسنل: ${names} - ساعت ${entryTime}`;
                entryData.personnel = selectedOptions;
                entryData.displayText = names;
                
            } else if (selectedType === 'guest') {
                const name = document.querySelector('input[name="guest_name"]').value;
                const phone = document.querySelector('input[name="guest_phone"]').value;
                const dest = document.querySelector('input[name="guest_destination"]').value;
                
                if (!name) {
                    alert('نام مهمان را وارد کنید');
                    return;
                }
                
                let details = [];
                if (name) details.push(name);
                if (phone) details.push(`📞 ${phone}`);
                if (dest) details.push(`🏢 ${dest}`);
                
                entryText = `👤 مهمان: ${details.join(' - ')} - ساعت ${entryTime}`;
                entryData.guest = { name, phone, destination: dest };
                entryData.displayText = name;
                
            } else if (selectedType === 'other') {
                const desc = document.querySelector('input[name="other_people_desc"]').value;
                if (!desc) {
                    alert('توضیحات را وارد کنید');
                    return;
                }
                entryText = `👤 سایر: ${desc} - ساعت ${entryTime}`;
                entryData.other = desc;
                entryData.displayText = desc;
            }
            
            // اضافه کردن به آرایه
            peopleEntries.push(entryData);
            
            // به‌روزرسانی نمایش
            updateEntriesList();
            
            // پاک کردن فرم
            clearPeopleForm();
        });

        function clearPeopleForm() {
            document.querySelector('select[name="personnel_ids[]"]').selectedIndex = -1;
            document.querySelector('input[name="guest_name"]').value = '';
            document.querySelector('input[name="guest_phone"]').value = '';
            document.querySelector('input[name="guest_destination"]').value = '';
            document.querySelector('input[name="other_people_desc"]').value = '';
        }

        function updateEntriesList() {
            const container = document.getElementById('entriesContainer');
            const hiddenField = document.getElementById('peopleEntriesData');
            
            if (peopleEntries.length === 0) {
                container.innerHTML = '<div style="color: #999; text-align: center; padding: 20px;">هیچ ورودی ثبت نشده</div>';
                hiddenField.value = '';
                return;
            }
            
            let html = '';
            peopleEntries.forEach((entry, index) => {
                let entryText = '';
                if (entry.type === 'personnel') {
                    entryText = `👥 پرسنل: ${entry.displayText} - ساعت ${entry.time}`;
                } else if (entry.type === 'guest') {
                    entryText = `👤 مهمان: ${entry.displayText} - ساعت ${entry.time}`;
                } else {
                    entryText = `👤 سایر: ${entry.displayText} - ساعت ${entry.time}`;
                }
                
                html += `
                    <div class="entry-item">
                        <span>${entryText}</span>
                        <button type="button" onclick="removeEntry(${index})" class="remove-btn">✕</button>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            hiddenField.value = JSON.stringify(peopleEntries);
        }

        window.removeEntry = function(index) {
            peopleEntries.splice(index, 1);
            updateEntriesList();
        };

        // ========== نمایش/مخفی کردن بخش‌های شرطی ==========
        
        // شماره اموال
        document.getElementById('propertyHasNumber')?.addEventListener('change', function() {
            document.getElementById('propertyNumberField').style.display = this.checked ? 'block' : 'none';
        });

        // روشنایی
        document.getElementById('lightingCheck')?.addEventListener('change', function() {
            document.getElementById('lightingDetails').style.display = this.checked ? 'block' : 'none';
        });

        // حفاظتی
        document.getElementById('protectiveCheck')?.addEventListener('change', function() {
            document.getElementById('protectiveDetails').style.display = this.checked ? 'block' : 'none';
        });

        // سرمایشی/گرمایشی
        document.getElementById('coolingHeatingCheck')?.addEventListener('change', function() {
            document.getElementById('coolingHeatingDetails').style.display = this.checked ? 'block' : 'none';
        });

        // بخاری و گاز
        document.getElementById('heaterGasCheck')?.addEventListener('change', function() {
            document.getElementById('heaterGasDetails').style.display = this.checked ? 'block' : 'none';
        });

        // تحویل شیفت
        document.getElementById('isHandover')?.addEventListener('change', function() {
            document.getElementById('handoverFields').style.display = this.checked ? 'block' : 'none';
        });
    </script>
</body>

</html>