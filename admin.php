<?php
session_start();
require_once 'classes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    die('Invalid CSRF token');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: auth.php");
    exit();
}

$userModel = new User();
$guardsObjects = $userModel->getAllGuards();
$scheduleModel = new GuardSchedule();

$autoScheduleMessage = '';
if (isset($_POST['auto_schedule'])) {
    $targetYear = (int)$_POST['target_year'];
    $targetMonth = (int)$_POST['target_month'];

    $prevMonth = $targetMonth - 1;
    $prevYear = $targetYear;
    if ($prevMonth < 1) {
        $prevMonth = 12;
        $prevYear--;
    }

    $daysInPrevMonth = JalaliDate::getMonthDays($prevYear, $prevMonth);
    $prevSchedule = $scheduleModel->getMonthSchedule($prevYear, $prevMonth);

    $guards = [];
    foreach ($guardsObjects as $guard) {
        $guards[] = $guard;
    }
    $guardCount = count($guards);

    $lastGuardId = null;
    $lastGuardIndex = -1;

    if (!empty($prevSchedule) && isset($prevSchedule[$daysInPrevMonth])) {
        $lastGuardId = $prevSchedule[$daysInPrevMonth];
        foreach ($guards as $index => $guard) {
            if ($guard->getId() == $lastGuardId) {
                $lastGuardIndex = $index;
                break;
            }
        }
    } else {
        $dayOfYear = JalaliDate::getDayOfYear($prevYear, $prevMonth, $daysInPrevMonth);
        $lastGuardIndex = ($dayOfYear - 1) % $guardCount;
    }

    $daysInTargetMonth = JalaliDate::getMonthDays($targetYear, $targetMonth);
    $nextGuardIndex = ($lastGuardIndex + 1) % $guardCount;

    $successCount = 0;
    for ($day = 1; $day <= $daysInTargetMonth; $day++) {
        $guardIndex = ($nextGuardIndex + $day - 1) % $guardCount;
        $guardId = $guards[$guardIndex]->getId();

        if ($scheduleModel->setSchedule($targetYear, $targetMonth, $day, $guardId)) {
            $successCount++;
        }
    }

    $autoScheduleMessage = "✅ شیفت‌بندی $successCount روز از $daysInTargetMonth روز با موفقیت تنظیم شد!";
}

if (isset($_POST['clear_schedule'])) {
    $clearYear = (int)$_POST['clear_year'];
    $clearMonth = (int)$_POST['clear_month'];
    $daysInMonth = JalaliDate::getMonthDays($clearYear, $clearMonth);

    $deletedCount = 0;
    for ($day = 1; $day <= $daysInMonth; $day++) {
        if ($scheduleModel->deleteSchedule($clearYear, $clearMonth, $day)) {
            $deletedCount++;
        }
    }

    $autoScheduleMessage = "🗑️ $deletedCount رکورد شیفت حذف شد!";
}

$dateInput = isset($_GET['date']) ? $_GET['date'] : '';
if ($dateInput && strlen($dateInput) == 7) {
    $year = (int)substr($dateInput, 0, 4);
    $month = (int)substr($dateInput, 5, 2);
} else {
    $today = JalaliDate::getToday();
    $year = $today['year'];
    $month = $today['month'];
}

$monthNames = JalaliDate::$month_names;

$shiftModel = new Shift();
$allShifts = $shiftModel->getAllShifts();

$settingModel = new ShiftSetting();
$activeSetting = $settingModel->getActiveSetting();
$shiftStartMinutes = $activeSetting->getStartMinutes();
$shiftEndMinutes = $activeSetting->getEndMinutes();

$standardShiftMinutes = 24 * 60;

function minutesToTime($minutes)
{
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%02d:%02d", $hours, $mins);
}

function formatDuration($minutes)
{
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    if ($hours > 0 && $mins > 0) {
        return $hours . " ساعت و " . $mins . " دقیقه";
    } elseif ($hours > 0) {
        return $hours . " ساعت";
    } else {
        return $mins . " دقیقه";
    }
}

$todayGregorian = date('Y-m-d');
$todayJalali = JalaliDate::getToday();

$guards = [];
foreach ($guardsObjects as $guard) {
    $guards[] = $guard;
}

$daysInMonth = JalaliDate::getMonthDays($year, $month);
$guardSchedule = [];
$guardCount = count($guards);

$dbSchedule = $scheduleModel->getMonthSchedule($year, $month);

if ($guardCount > 0) {
    for ($day = 1; $day <= $daysInMonth; $day++) {
        if (isset($dbSchedule[$day])) {
            foreach ($guards as $guard) {
                if ($guard->getId() == $dbSchedule[$day]) {
                    $guardSchedule[$day] = $guard;
                    break;
                }
            }
        } else {
            $dayOfYear = JalaliDate::getDayOfYear($year, $month, $day);
            $guardIndex = ($dayOfYear - 1) % $guardCount;
            $guardSchedule[$day] = $guards[$guardIndex];
        }
    }
}

$shiftsByDay = [];
foreach ($allShifts as $shift) {
    $startTime = strtotime($shift->getStartTime());
    $startDate = date('Y-m-d', $startTime);
    $startParts = explode('-', $startDate);
    $jalaliStart = JalaliDate::gregorianToJalali($startParts[0], $startParts[1], $startParts[2]);

    if ($jalaliStart['year'] == $year && $jalaliStart['month'] == $month) {
        $day = $jalaliStart['day'];
        if (!isset($shiftsByDay[$day])) $shiftsByDay[$day] = [];

        $userId = $shift->getUser()->getId();
        if (!isset($shiftsByDay[$day][$userId])) {
            $shiftsByDay[$day][$userId] = [
                'user' => $shift->getUser(),
                'shifts' => []
            ];
        }
        $shiftsByDay[$day][$userId]['shifts'][] = $shift;
    }
}

foreach ($shiftsByDay as $day => &$users) {
    foreach ($users as &$userData) {
        usort($userData['shifts'], function ($a, $b) {
            return strtotime($a->getStartTime()) - strtotime($b->getStartTime());
        });
    }
}
unset($users, $userData);

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$daysInPrevMonth = JalaliDate::getMonthDays($prevYear, $prevMonth);
$prevDbSchedule = $scheduleModel->getMonthSchedule($prevYear, $prevMonth);
$lastScheduledGuard = 'نامشخص';

if (!empty($prevDbSchedule) && isset($prevDbSchedule[$daysInPrevMonth])) {
    foreach ($guards as $guard) {
        if ($guard->getId() == $prevDbSchedule[$daysInPrevMonth]) {
            $lastScheduledGuard = $guard->getName();
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گانت تقویمی شیفت‌ها</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .tooltip {
            position: fixed;
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            z-index: 1000;
            display: none;
            max-width: 350px;
            word-wrap: break-word;
            line-height: 1.6;
        }

        .tooltip.show {
            display: block;
        }

        .shift-block {
            position: absolute;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            overflow: hidden;
            white-space: nowrap;
        }

        .gap-block {
            background: #9b59b6;
            z-index: 5;
        }

        .user-name-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
            z-index: 10;
            pointer-events: none;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
        }

        .multiple-guards {
            display: flex;
            flex-direction: column;
            gap: 2px;
            height: 100%;
        }

        .guard-row {
            position: relative;
            height: 40px;
            flex: 1;
            min-height: 35px;
        }

        .test-date {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            text-align: center;
            font-weight: bold;
            color: #1976d2;
        }

        .schedule-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            color: white;
        }

        .schedule-panel h3 {
            margin-top: 0;
            margin-bottom: 15px;
        }

        .schedule-info {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .btn-auto {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            margin: 5px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-clear {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            margin: 5px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
            border: 2px solid #28a745;
        }

        .db-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-left: 5px;
        }

        .db-indicator.saved {
            background: #28a745;
        }

        .db-indicator.calculated {
            background: #ffc107;
        }

        .incomplete-badge {
            position: absolute;
            top: -5px;
            left: 5px;
            background: #e74c3c;
            color: white;
            font-size: 10px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 10px;
            z-index: 20;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.6);
            }

            50% {
                box-shadow: 0 0 10px 3px rgba(231, 76, 60, 0.3);
            }
        }

        .chart-box.incomplete {
            border: 2px solid #e74c3c;
            border-radius: 4px;
        }

        /* ✅ استایل‌های جدید برای نوار ثانویه (اضافه کار) */
        .secondary-shift-container {
            position: absolute;
            bottom: 2px;
            left: 0;
            right: 0;
            height: 16px;
            background: transparent;
            z-index: 15;
        }

        .secondary-shift-block {
            position: absolute;
            height: 100%;
            background: #f39c12;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 9px;
            overflow: hidden;
            white-space: nowrap;
            border: 1px solid #e67e22;
        }

        .secondary-shift-name {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 9px;
            font-weight: bold;
            z-index: 16;
            pointer-events: none;
            text-shadow: 1px 1px 1px rgba(0, 0, 0, 0.5);
        }

        .secondary-active {
            border: 1px solid #ffd700;
            animation: pulse-gold 2s infinite;
        }

        @keyframes pulse-gold {

            0%,
            100% {
                box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.6);
            }

            50% {
                box-shadow: 0 0 8px 2px rgba(255, 215, 0, 0.4);
            }
        }

        /* ✅ کوچکتر کردن چارت اصلی */
        .chart-box {
            flex: 1;
            position: relative;
            min-height: 50px !important;
            height: auto !important;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(248, 249, 250, 0.5) 100%);
            padding: 4px;
        }

        .day-row {
            display: flex;
            border-bottom: 1px solid #e0e0e0;
            min-height: 60px !important;
            height: auto !important;
            background: white;
            position: relative;
        }
    </style>
</head>

<body>
    <div class="card admin-panel">
        <div class="test-date">
            📅 امروز: <?php echo JalaliDate::format($todayJalali['year'], $todayJalali['month'], $todayJalali['day'], 'l، d F Y'); ?>
            (میلادی: <?php echo date('Y-m-d l'); ?>)
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="logout.php" class="logout-link">خروج از سیستم</a>
        </div>
        <h1 style="text-align: center;">📅 گانت تقویمی شیفت‌ها</h1>

        <?php if ($autoScheduleMessage): ?>
            <div class="success-message"><?php echo $autoScheduleMessage; ?></div>
        <?php endif; ?>

        <div class="schedule-panel">
            <h3>⚙️ مدیریت شیفت‌بندی</h3>

            <div class="schedule-info">
                <strong>ماه جاری:</strong> <?php echo $monthNames[$month] . ' ' . $year; ?><br>
                <strong>ماه قبل:</strong> <?php echo $monthNames[$prevMonth] . ' ' . $prevYear; ?><br>
                <strong>آخرین نگهبان ماه قبل:</strong> <?php echo htmlspecialchars($lastScheduledGuard); ?>
                (<?php echo !empty($prevDbSchedule) ? 'از دیتابیس' : 'محاسبه شده'; ?>)
            </div>

            <form method="post" style="display: inline-block;">
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <input type="hidden" name="target_year" value="<?php echo $year; ?>">
                <input type="hidden" name="target_month" value="<?php echo $month; ?>">

                <button type="submit" name="auto_schedule" class="btn-auto" onclick="return confirm('آیا مطمئن هستید؟ این عملیات شیفت‌بندی <?php echo $daysInMonth; ?> روز را تنظیم می‌کند.');">
                    🔄 تنظیم خودکار شیفت این ماه
                </button>
            </form>

            <form method="post" style="display: inline-block;">
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <input type="hidden" name="clear_year" value="<?php echo $year; ?>">
                <input type="hidden" name="clear_month" value="<?php echo $month; ?>">

                <button type="submit" name="clear_schedule" class="btn-clear" onclick="return confirm('⚠️ همه شیفت‌های این ماه حذف شوند؟');">
                    🗑️ حذف شیفت‌های این ماه
                </button>
            </form>
        </div>

        <div class="shift-info-bar" style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;">
            <strong>⏰ ساعت کاری:</strong>
            از <?php echo htmlspecialchars($activeSetting->getStartTimeFormatted()); ?>
            تا <?php echo htmlspecialchars($activeSetting->getEndTimeFormatted()); ?> فردا
            (۲۴ ساعت)
            <br>
            <small>
                ترتیب نگهبانان:
                <?php
                $guardNames = [];
                foreach ($guards as $g) $guardNames[] = htmlspecialchars($g->getName());
                echo implode(' → ', $guardNames);
                ?>
                <br>
                <span style="color: #666; font-size: 11px;">
                    <span class="db-indicator saved"></span> = ذخیره شده در دیتابیس |
                    <span class="db-indicator calculated"></span> = محاسبه شده |
                    <span style="color: #e74c3c;">🔴</span> = ساعت ناقص |
                    <span style="color: #f39c12;">🟠</span> = اضافه کار (نفر دوم)
                </span>
            </small>
        </div>

        <div class="month-picker">
            <form method="GET">
                <input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">
                <label>ماه:</label>
                <input type="month" name="date" value="<?php echo sprintf("%04d-%02d", $year, $month); ?>" onchange="this.form.submit()">
            </form>
        </div>

        <div class="legend-bar">
            <div class="legend-item">
                <div class="legend-color" style="background: #e0e0e0; border: 2px dashed #999;"></div>
                <span>شیفت برنامه‌ریزی شده</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #3498db;"></div>
                <span>زودتر (قبل)</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #e74c3c;"></div>
                <span>تاخیر</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #27ae60;"></div>
                <span>شیفت اصلی</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #9b59b6;"></div>
                <span>غیبت</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #f39c12;"></div>
                <span>اضافه بعد</span>
            </div>
        </div>

        <div class="gantt-wrapper">
            <div class="gantt-title">
                <h2>
                    <?php echo $monthNames[$month]; ?> <?php echo $year; ?>
                    <?php
                    $dbCount = count($dbSchedule);
                    if ($dbCount > 0) {
                        echo '<span style="font-size: 14px; color: #d4edda;">( ' . $dbCount . ' روز از دیتابیس )</span>';
                    }
                    ?>
                </h2>
                <div class="gantt-subtitle">۶ صبح ← ۲۴ ساعت ← ۶ صبح فردا</div>
            </div>

            <div class="time-header">
                <div class="time-label">روز</div>
                <div class="time-scale" style="direction: ltr;">
                    <?php
                    $hours = [6, 9, 12, 15, 18, 21, 0, 3, 6];
                    foreach ($hours as $index => $h):
                        $label = sprintf("%02d:00", $h);
                        $percent = ($index / (count($hours) - 1)) * 100;
                    ?>
                        <div class="hour-marker" style="right: <?php echo $percent; ?>%; left: auto;"><?php echo $label; ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $gregorian = JalaliDate::jalaliToGregorian($year, $month, $day);
                $dateStr = sprintf("%04d-%02d-%02d", $gregorian[0], $gregorian[1], $gregorian[2]);

                $dayOfWeek = JalaliDate::getWeekday($year, $month, $day);

                $isFriday = ($dayOfWeek == 'جمعه');
                $isToday = ($dateStr == $todayGregorian);
                $dayUsers = isset($shiftsByDay[$day]) ? $shiftsByDay[$day] : [];
                $scheduledGuard = isset($guardSchedule[$day]) ? $guardSchedule[$day] : null;
                $hasCheckin = !empty($dayUsers);
                $userCount = count($dayUsers);

                $isFromDb = isset($dbSchedule[$day]);

                // ✅ جدا کردن کاربر اصلی از اضافه کار
                $primaryUser = null;
                $secondaryUsers = [];

                if ($hasCheckin && $scheduledGuard) {
                    foreach ($dayUsers as $userId => $userData) {
                        if ($userData['user']->getId() == $scheduledGuard->getId()) {
                            $primaryUser = $userData;
                        } else {
                            $secondaryUsers[] = $userData;
                        }
                    }
                } else {
                    // اگر شیفت مشخص نیست، اولی اصلیه، بقیه اضافه
                    $first = true;
                    foreach ($dayUsers as $userId => $userData) {
                        if ($first) {
                            $primaryUser = $userData;
                            $first = false;
                        } else {
                            $secondaryUsers[] = $userData;
                        }
                    }
                }

                // ✅ محاسبه ساعت ناقص برای کاربر اصلی
                $isIncomplete = false;
                if ($primaryUser) {
                    $totalWorked = 0;
                    foreach ($primaryUser['shifts'] as $shift) {
                        $s = strtotime($shift->getStartTime());
                        $e = $shift->getEndTime() ? strtotime($shift->getEndTime()) : time();
                        $totalWorked += ($e - $s) / 60;
                    }
                    $isIncomplete = ($totalWorked < $standardShiftMinutes);
                }
            ?>
                <div class="day-row <?php echo $isToday ? 'today' : ''; ?>" style="<?php echo ($userCount > 1 || !empty($secondaryUsers)) ? 'min-height: 75px !important;' : ''; ?>">
                    <div class="day-cell <?php echo $isFriday ? 'friday' : ''; ?>">
                        <div class="day-num">
                            <?php echo $day; ?>
                            <span class="db-indicator <?php echo $isFromDb ? 'saved' : 'calculated'; ?>"></span>
                        </div>
                        <div class="day-name"><?php echo $dayOfWeek; ?></div>
                    </div>

                    <div class="chart-box <?php echo $isIncomplete ? 'incomplete' : ''; ?>" style="position: relative;">

                        <?php if ($isIncomplete): ?>
                            <div class="incomplete-badge">⚠️ ناقص</div>
                        <?php endif; ?>

                        <?php if ($scheduledGuard && !$hasCheckin): ?>
                            <div class="scheduled-guard" style="right: 0%; width: 100%; height: 100%;">
                                <?php echo htmlspecialchars($scheduledGuard->getName()); ?>
                                <?php echo $isFromDb ? '(ذخیره)' : '(محاسبه)'; ?>
                            </div>
                        <?php elseif ($primaryUser): ?>

                            <?php
                            // ✅ نمایش کاربر اصلی (شیفت وظیفه‌ای)
                            $user = $primaryUser['user'];
                            $userShifts = $primaryUser['shifts'];

                            $firstShift = $userShifts[0];
                            $lastShift = $userShifts[count($userShifts) - 1];

                            $firstStartTime = strtotime($firstShift->getStartTime());
                            $lastEndTime = $lastShift->getEndTime() ? strtotime($lastShift->getEndTime()) : time();

                            $firstStartStr = date('H:i', $firstStartTime);
                            $lastEndStr = $lastShift->getEndTime() ? date('H:i', strtotime($lastShift->getEndTime())) : 'فعال';

                            $firstStartMinutes = (int)date('H', $firstStartTime) * 60 + (int)date('i', $firstStartTime);

                            $totalWorkedMinutes = 0;
                            $isActive = false;
                            $segments = [];

                            for ($i = 0; $i < count($userShifts); $i++) {
                                $shift = $userShifts[$i];
                                $sTime = strtotime($shift->getStartTime());
                                $eTime = $shift->getEndTime() ? strtotime($shift->getEndTime()) : time();

                                if (!$shift->getEndTime()) {
                                    $isActive = true;
                                }

                                $sMinutes = (int)date('H', $sTime) * 60 + (int)date('i', $sTime);
                                $eMinutes = (int)date('H', $eTime) * 60 + (int)date('i', $eTime);

                                if (date('Y-m-d', $eTime) > date('Y-m-d', $sTime)) {
                                    $eMinutes += 24 * 60;
                                }

                                $duration = ($eTime - $sTime) / 60;
                                $totalWorkedMinutes += $duration;

                                $segments[] = [
                                    'type' => 'work',
                                    'start' => $sMinutes,
                                    'end' => $eMinutes,
                                    'duration' => $duration
                                ];

                                if ($i < count($userShifts) - 1) {
                                    $nextShift = $userShifts[$i + 1];
                                    $nextStartTime = strtotime($nextShift->getStartTime());
                                    $nextSMinutes = (int)date('H', $nextStartTime) * 60 + (int)date('i', $nextStartTime);

                                    if (date('Y-m-d', $nextStartTime) > date('Y-m-d', $sTime)) {
                                        $nextSMinutes += 24 * 60;
                                    }

                                    if ($nextSMinutes > $eMinutes) {
                                        $segments[] = [
                                            'type' => 'gap',
                                            'start' => $eMinutes,
                                            'end' => $nextSMinutes,
                                            'duration' => $nextSMinutes - $eMinutes
                                        ];
                                    }
                                }
                            }

                            $earlyMinutes = 0;
                            $lateMinutes = 0;
                            if ($firstStartMinutes < $shiftStartMinutes) {
                                $earlyMinutes = $shiftStartMinutes - $firstStartMinutes;
                            } elseif ($firstStartMinutes > $shiftStartMinutes) {
                                $lateMinutes = $firstStartMinutes - $shiftStartMinutes;
                            }

                            $totalGapMinutes = 0;
                            foreach ($segments as $seg) {
                                if ($seg['type'] == 'gap') {
                                    $totalGapMinutes += $seg['duration'];
                                }
                            }

                            $extraAfterMinutes = 0;
                            if ($totalWorkedMinutes > $standardShiftMinutes) {
                                $extraAfterMinutes = $totalWorkedMinutes - $standardShiftMinutes;
                            }

                            $tooltip = "<strong>" . htmlspecialchars($user->getName()) . "</strong> (شیفت اصلی)<br>";
                            $tooltip .= "ورود: " . $firstStartStr . " | خروج: " . $lastEndStr;

                            if ($totalGapMinutes > 0) {
                                $tooltip .= "<br>غیبت: " . minutesToTime((int)$totalGapMinutes);
                            }

                            $tooltip .= "<br>";

                            if ($lateMinutes > 0) {
                                $tooltip .= "تاخیر: " . minutesToTime($lateMinutes) . " | ";
                            }
                            if ($earlyMinutes > 0) {
                                $tooltip .= "زودتر: " . minutesToTime($earlyMinutes) . " | ";
                            }

                            if ($totalWorkedMinutes >= $standardShiftMinutes) {
                                $tooltip .= "24 ساعت";
                                if ($extraAfterMinutes > 0) {
                                    $tooltip .= " + " . formatDuration((int)$extraAfterMinutes) . " اضافه";
                                }
                                $tooltip .= " ✓";
                            } else {
                                $shortage = $standardShiftMinutes - $totalWorkedMinutes;
                                $tooltip .= "<span style='color:#ff6b6b;'>⚠️ ناقص: " . formatDuration((int)$shortage) . "</span>";
                            }

                            if ($isActive) {
                                $tooltip .= "<br>⚡ فعال";
                            }

                            $dayMinutes = 24 * 60;
                            ?>

                            <div class="user-shift-container" style="position: relative; width: 100%; height: 32px;">

                                <?php
                                if ($earlyMinutes > 0):
                                    $width = ($earlyMinutes / $dayMinutes) * 100;
                                ?>
                                    <div class="shift-block" style="right: 0%; width: <?php echo $width; ?>%; background: #3498db; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                <?php endif; ?>

                                <?php
                                if ($lateMinutes > 0):
                                    $width = ($lateMinutes / $dayMinutes) * 100;
                                    $startRight = (($shiftStartMinutes - $firstStartMinutes) / $dayMinutes) * 100;
                                ?>
                                    <div class="shift-block" style="right: <?php echo $startRight; ?>%; width: <?php echo $width; ?>%; background: #e74c3c; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                <?php endif; ?>

                                <?php
                                foreach ($segments as $segment):
                                    $segWidth = ($segment['duration'] / $dayMinutes) * 100;
                                    $segStart = $segment['start'];

                                    $segRight = (($segStart - $firstStartMinutes) / $dayMinutes) * 100;

                                    if ($segment['type'] == 'work'):
                                        $workedBefore = 0;
                                        foreach ($segments as $prev) {
                                            if ($prev === $segment) break;
                                            if ($prev['type'] == 'work') {
                                                $workedBefore += $prev['duration'];
                                            }
                                        }

                                        if ($workedBefore >= $standardShiftMinutes) {
                                ?>
                                            <div class="shift-block" style="right: <?php echo $segRight; ?>%; width: <?php echo $segWidth; ?>%; background: #f39c12; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>">
                                                <?php if ($segWidth > 8): ?>
                                                    <small>+<?php echo minutesToTime((int)$segment['duration']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php
                                        } elseif ($workedBefore + $segment['duration'] > $standardShiftMinutes) {
                                            $mainPart = $standardShiftMinutes - $workedBefore;
                                            $extraPart = $segment['duration'] - $mainPart;
                                            $mainWidth = ($mainPart / $dayMinutes) * 100;
                                            $extraWidth = ($extraPart / $dayMinutes) * 100;
                                        ?>
                                            <div class="shift-block" style="right: <?php echo $segRight; ?>%; width: <?php echo $mainWidth; ?>%; background: #27ae60; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                            <div class="shift-block" style="right: <?php echo $segRight + $mainWidth; ?>%; width: <?php echo $extraWidth; ?>%; background: #f39c12; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>">
                                                <?php if ($extraWidth > 8): ?>
                                                    <small>+<?php echo minutesToTime((int)$extraPart); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php
                                        } else {
                                        ?>
                                            <div class="shift-block" style="right: <?php echo $segRight; ?>%; width: <?php echo $segWidth; ?>%; background: #27ae60; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                        <?php
                                        }
                                    else: // gap
                                        ?>
                                        <div class="shift-block gap-block" style="right: <?php echo $segRight; ?>%; width: <?php echo max($segWidth, 1); ?>%; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                <?php
                                    endif;
                                endforeach;
                                ?>

                                <div class="user-name-overlay" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>" style="height: 100%;">
                                    <?php echo htmlspecialchars($user->getName()); ?>
                                </div>

                            </div>

                            <?php
                            // ✅ نمایش کاربران ثانویه (اضافه کار) - نوار باریک پایین
                            foreach ($secondaryUsers as $secUserData):
                                $secUser = $secUserData['user'];
                                $secShifts = $secUserData['shifts'];

                                $secFirst = $secShifts[0];
                                $secLast = $secShifts[count($secShifts) - 1];

                                $secStartTime = strtotime($secFirst->getStartTime());
                                $secEndTime = $secLast->getEndTime() ? strtotime($secLast->getEndTime()) : time();

                                $secStartStr = date('H:i', $secStartTime);
                                $secEndStr = $secLast->getEndTime() ? date('H:i', strtotime($secLast->getEndTime())) : 'فعال';

                                $secStartMinutes = (int)date('H', $secStartTime) * 60 + (int)date('i', $secStartTime);
                                $secEndMinutes = (int)date('H', $secEndTime) * 60 + (int)date('i', $secEndTime);

                                if (date('Y-m-d', $secEndTime) > date('Y-m-d', $secStartTime)) {
                                    $secEndMinutes += 24 * 60;
                                }

                                $secDuration = ($secEndTime - $secStartTime) / 60;
                                $secIsActive = !$secLast->getEndTime();

                                // محاسبه موقعیت
                                $secRight = (($secStartMinutes - $firstStartMinutes) / $dayMinutes) * 100;
                                $secWidth = (($secEndMinutes - $secStartMinutes) / $dayMinutes) * 100;

                                // اگر منفی شد، از 0 شروع کن
                                if ($secRight < 0) {
                                    $secWidth += $secRight;
                                    $secRight = 0;
                                }
                                if ($secWidth > 100) $secWidth = 100;

                                $secTooltip = "<strong>" . htmlspecialchars($secUser->getName()) . "</strong> (اضافه کار)<br>";
                                $secTooltip .= "ورود: " . $secStartStr . " | خروج: " . $secEndStr . "<br>";
                                $secTooltip .= "مدت: " . formatDuration((int)$secDuration);
                                if ($secIsActive) {
                                    $secTooltip .= "<br>⚡ فعال";
                                }
                            ?>

                                <div class="secondary-shift-container" data-tooltip="<?php echo htmlspecialchars($secTooltip); ?>">
                                    <div class="secondary-shift-block <?php echo $secIsActive ? 'secondary-active' : ''; ?>"
                                        style="right: <?php echo max(0, $secRight); ?>%; width: <?php echo $secWidth; ?>%;">
                                        <span style="z-index: 16; position: relative;">
                                            <?php echo htmlspecialchars($secUser->getName()); ?>
                                            (<?php echo $secStartStr; ?>-<?php echo $secEndStr; ?>)
                                        </span>
                                    </div>
                                </div>

                            <?php endforeach; ?>

                        <?php else: ?>
                            <div class="no-shift">-</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>

    <div class="tooltip" id="tooltip"></div>

    <script>
        const tooltip = document.getElementById('tooltip');

        document.querySelectorAll('[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', (e) => {
                const content = e.target.getAttribute('data-tooltip');
                tooltip.innerHTML = content;
                tooltip.classList.add('show');
            });

            el.addEventListener('mousemove', (e) => {
                const tooltipWidth = tooltip.offsetWidth;
                const tooltipHeight = tooltip.offsetHeight;
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;

                let left = e.clientX + 15;
                let top = e.clientY - tooltipHeight - 10;

                if (left + tooltipWidth > windowWidth) {
                    left = e.clientX - tooltipWidth - 15;
                }
                if (left < 0) left = 10;

                if (top < 0) {
                    top = e.clientY + 20;
                }
                if (top + tooltipHeight > windowHeight) {
                    top = windowHeight - tooltipHeight - 10;
                }

                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
            });

            el.addEventListener('mouseleave', () => {
                tooltip.classList.remove('show');
            });
        });
    </script>
</body>

</html>