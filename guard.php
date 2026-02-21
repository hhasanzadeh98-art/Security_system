<?php
session_start();
require_once 'classes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guard') {
    header("Location: auth.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$userModel = new User();
$shiftObj = new Shift();
$active_shift = $shiftObj->getActiveShift($user_id);

$allGuards = $userModel->getAllGuards();
$guards = [];
foreach ($allGuards as $guard) {
    $guards[] = $guard;
}

// ✅ استفاده از کلاس JalaliDate برای تاریخ امروز
$todayJalali = JalaliDate::getToday();
$gregorianParts = JalaliDate::jalaliToGregorian($todayJalali['year'], $todayJalali['month'], $todayJalali['day']);

// محاسبه شیفت‌بندی چرخشی
$guardCount = count($guards);
$scheduledGuardId = null;
$todayGuard = null;

if ($guardCount > 0) {
    $dayOfYear = JalaliDate::getDayOfYear($todayJalali['year'], $todayJalali['month'], $todayJalali['day']);
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

    $shiftDayOfYear = JalaliDate::getDayOfYear($shiftJalali['year'], $shiftJalali['month'], $shiftJalali['day']);
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

// ✅ استفاده از توابع صحیح JalaliDate
$monthNames = JalaliDate::$month_names;
$todayJalaliFormatted = JalaliDate::format($todayJalali['year'], $todayJalali['month'], $todayJalali['day'], 'd F Y');
$todayWeekday = JalaliDate::getWeekday($todayJalali['year'], $todayJalali['month'], $todayJalali['day']);

function convertToJalaliDateTime($datetime)
{
    $timestamp = strtotime($datetime);
    $g_y = date('Y', $timestamp);
    $g_m = date('m', $timestamp);
    $g_d = date('d', $timestamp);
    $time = date('H:i:s', $timestamp);

    $jalali = JalaliDate::gregorianToJalali($g_y, $g_m, $g_d);
    $monthNames = JalaliDate::$month_names;

    return $jalali['day'] . ' ' . $monthNames[$jalali['month']] . ' ' . $jalali['year'] . ' - ' . $time;
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل نگهبان</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .test-date {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            color: #1976d2;
            font-size: 13px;
        }
    </style>
</head>

<body>
    <div class="card">
        <!-- ✅ نمایش تاریخ صحیح برای تست -->
        <div class="test-date">
            📅 امروز: <?php echo JalaliDate::format($todayJalali['year'], $todayJalali['month'], $todayJalali['day'], 'l، d F Y'); ?>
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
                <?php echo convertToJalaliDateTime($active_shift->getStartTime()); ?>
            </div>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <button type="submit" name="end_shift" class="btn btn-end">پایان شیفت (خروج)</button>
            </form>

        <?php else: ?>

            <?php if ($isScheduledDay): ?>
                <div class="shift-info shift-scheduled">
                    ✅ <strong>امروز روز شیفت شماست</strong><br>
                    نوبت شیفت: شما
                </div>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                    <button type="submit" name="start_shift" class="btn btn-start">شروع شیفت (ورود)</button>
                </form>

            <?php else: ?>
                <div class="shift-info shift-not-scheduled">
                    ❌ <strong>امروز روز شیفت شما نیست</strong><br>
                    نوبت شیفت: <span class="guard-name"><?php echo $todayGuard ? htmlspecialchars($todayGuard->getName()) : 'نامشخص'; ?></span>
                </div>

                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
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

        <a href="logout.php" class="logout-link">خروج از حساب کاربری</a>
    </div>
</body>

</html>