<?php
session_start();
require_once 'classes.php';

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

    $prevScheduleVars = get_object_vars($prevSchedule);
    if (!empty($prevScheduleVars) && isset($prevSchedule->{$daysInPrevMonth})) {
        $lastGuardId = $prevSchedule->{$daysInPrevMonth};
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
    $year = $today->year;
    $month = $today->month;
}

$monthNames = JalaliDate::$month_names;

$shiftModel = new Shift();
$allShifts = $shiftModel->getAllShifts();

$settingModel = new ShiftSetting();
$activeSetting = $settingModel->getActiveSetting();
$shiftStartMinutes = $activeSetting->getStartMinutes();
$shiftEndMinutes = $activeSetting->getEndMinutes();

$standardShiftMinutes = 24 * 60;

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
        $dbScheduleVars = get_object_vars($dbSchedule);
        if (isset($dbSchedule->{$day})) {
            foreach ($guards as $guard) {
                if ($guard->getId() == $dbSchedule->{$day}) {
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

$shiftsByDay = new stdClass();
foreach ($allShifts as $shift) {
    $startTime = strtotime($shift->getStartTime());
    $startDate = date('Y-m-d', $startTime);
    $startParts = explode('-', $startDate);
    $jalaliStart = JalaliDate::gregorianToJalali($startParts[0], $startParts[1], $startParts[2]);

    if ($jalaliStart->year == $year && $jalaliStart->month == $month) {
        $day = $jalaliStart->day;
        if (!isset($shiftsByDay->{$day})) $shiftsByDay->{$day} = new stdClass();

        $userId = $shift->getUser()->getId();
        if (!isset($shiftsByDay->{$day}->{$userId})) {
            $shiftsByDay->{$day}->{$userId} = new stdClass();
            $shiftsByDay->{$day}->{$userId}->user = $shift->getUser();
            $shiftsByDay->{$day}->{$userId}->shifts = [];
        }
        $shiftsByDay->{$day}->{$userId}->shifts[] = $shift;
    }
}

foreach (get_object_vars($shiftsByDay) as $day => $users) {
    foreach (get_object_vars($users) as $userId => $userData) {
        usort($userData->shifts, function ($a, $b) {
            return strtotime($a->getStartTime()) - strtotime($b->getStartTime());
        });
    }
}

$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}
$daysInPrevMonth = JalaliDate::getMonthDays($prevYear, $prevMonth);
$prevDbSchedule = $scheduleModel->getMonthSchedule($prevYear, $prevMonth);
$lastScheduledGuard = 'نامشخص';

$prevDbScheduleVars = get_object_vars($prevDbSchedule);
if (!empty($prevDbScheduleVars) && isset($prevDbSchedule->{$daysInPrevMonth})) {
    foreach ($guards as $guard) {
        if ($guard->getId() == $prevDbSchedule->{$daysInPrevMonth}) {
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

</head>

<body>
    <div class="card admin-panel">
        <div class="test-date">
            📅 امروز: <?php echo JalaliDate::format($todayJalali->year, $todayJalali->month, $todayJalali->day, 'l، d F Y'); ?>
            (میلادی: <?php echo date('Y-m-d l'); ?>)
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="logout.php" class="logout-link">خروج از سیستم</a>
        </div>
        <h1 style="text-align: center;">📅شیفت ها</h1>
        <!-- بخش جدید: گزارش‌های نگهبان‌ها -->

        <div class="card" style="margin-top: 40px;">
            <h2 style="text-align:center;">📊 گزارش‌های شیفت نگهبان‌ها</h2>

            <!-- انتخاب ماه و سال -->
            <form method="GET" style="text-align:center; margin:20px 0;">
                <label>سال:</label>
                <select name="report_year">
                    <?php
                    $currentYear = $todayJalali->year;
                    for ($y = $currentYear - 2; $y <= $currentYear + 1; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == ($GET['report_year'] ?? $currentYear)) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <label>ماه:</label>
                <select name="report_month">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m == ($GET['report_month'] ?? $todayJalali->month)) ? 'selected' : ''; ?>>
                            <?php echo JalaliDate::$month_names->{$m}; ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <button type="submit" class="btn-primary" style="margin-right:15px;">نمایش گزارش‌ها</button>
            </form>

            <?php
            $reportYear  = (int)($_GET['report_year']  ?? $todayJalali->year);
            $reportMonth = (int)($_GET['report_month'] ?? $todayJalali->month);

            $reportModel = new GuardShiftReport(Database::getConnection());
            $monthReports = $reportModel->getMonthReports($reportYear, $reportMonth);

            if (empty($monthReports)):
            ?>
                <p style="text-align:center; color:#777; padding:30px;">
                    در این ماه هنوز هیچ گزارشی ثبت نشده است.
                </p>
            <?php else: ?>
                <table style="width:100%; border-collapse:collapse; margin-top:20px;">
                    <thead>
                        <tr style="background:#34495e; color:white;">
                            <th style="padding:12px;">تاریخ شمسی</th>
                            <th style="padding:12px;">نگهبان</th>
                            <th style="padding:12px;">شیفت</th>
                            <th style="padding:12px;">تیک‌ها</th>
                            <th style="padding:12px;">حوادث/یادداشت</th>
                            <th style="padding:12px;">تحویل پست</th>
                            <th style="padding:12px;">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthReports as $rep):
                            $jalaliDate = JalaliDate::format($rep->jalali_year, $rep->jalali_month, $rep->jalali_day, 'j F');
                            $checkedPercent = round(($rep->checked_count / 13) * 100); // 13 تا چک‌باکس داریم
                        ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:10px;"><?php echo $jalaliDate; ?></td>
                                <td style="padding:10px;"><?php echo htmlspecialchars($rep->guard_name ?? 'نامشخص'); ?></td>
                                <td style="padding:10px;"><?php echo $rep->shift_type === '24h' ? '۲۴ ساعته' : ($rep->shift_type === 'morning' ? 'صبح تا عصر' : 'عصر تا صبح'); ?></td>
                                <td style="padding:10px; text-align:center;">
                                    <?php echo $rep->checked_count; ?> / 13
                                    <span style="color:<?php echo $checkedPercent >= 80 ? '#27ae60' : ($checkedPercent >= 50 ? '#f39c12' : '#e74c3c'); ?>">
                                        (<?php echo $checkedPercent; ?>%)
                                    </span>
                                </td>
                                <td style="padding:10px; text-align:center;">
                                    <?php
                                    $hasNote = !empty($rep->incidents_text) || !empty($rep->contacts_text) || !empty($rep->notes_text);
                                    echo $hasNote ? '<span style="color:#27ae60;">بله</span>' : '<span style="color:#e74c3c;">خیر</span>';
                                    ?>
                                </td>
                                <td style="padding:10px; text-align:center;">
                                    <?php
                                    if ($rep->handover_time && $rep->handover_time !== '00:00:00') {
                                        echo '<span style="color:#27ae60;">' . htmlspecialchars($rep->handover_time) . '</span>';
                                    } else {
                                        echo '<span style="color:#e74c3c; font-weight:bold;">تحویل نداده</span>';
                                    }
                                    ?>
                                </td>
                                <td style="padding:10px; text-align:center;">
                                    <a href="#" onclick="alert('جزئیات کامل گزارش در آینده اضافه می‌شود'); return false;" style="color:#3498db; text-decoration:none;">
                                        مشاهده جزئیات
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="text-align:center; margin-top:20px; color:#777;">
                    تعداد کل گزارش‌های این ماه: <?php echo count($monthReports); ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($autoScheduleMessage): ?>
            <div class="success-message"><?php echo $autoScheduleMessage; ?></div>
        <?php endif; ?>

        <div class="schedule-panel">


            <h3>⚙️ مدیریت شیفت‌بندی</h3>

            <form method="post" style="display: inline-block;">
                <input type="hidden" name="target_year" value="<?php echo $year; ?>">
                <input type="hidden" name="target_month" value="<?php echo $month; ?>">

                <button type="submit" name="auto_schedule" class="btn-auto" onclick="return confirm('آیا مطمئن هستید؟ این عملیات شیفت‌بندی <?php echo $daysInMonth; ?> روز را تنظیم می‌کند.');">
                    🔄 تنظیم خودکار شیفت این ماه
                </button>
            </form>

        </div>

        <div class="month-picker">
            <form method="GET" id="monthForm">
                <input type="hidden" name="date" id="dateInput" value="<?php echo sprintf("%04d-%02d", $year, $month); ?>">

                <label>ماه:</label>
                <select id="yearSelect" onchange="updateDate()">
                    <?php for ($y = 1400; $y <= 1410; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo ($y == $year) ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>

                <select id="monthSelect" onchange="updateDate()">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo sprintf("%02d", $m); ?>" <?php echo ($m == $month) ? 'selected' : ''; ?>>
                            <?php echo $monthNames->{$m}; ?>
                        </option>
                    <?php endfor; ?>
                </select>
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
                <div class="legend-color" style="background: #ff6b6b;"></div>
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
                    <?php echo $monthNames->$month; ?> <?php echo $year; ?>
                </h2>
            </div>

            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $gregorian = JalaliDate::jalaliToGregorian($year, $month, $day);
                $dateStr = sprintf("%04d-%02d-%02d", $gregorian[0], $gregorian[1], $gregorian[2]);

                $dayOfWeek = JalaliDate::getWeekday($year, $month, $day);

                $isFriday = ($dayOfWeek == 'جمعه');
                $isToday = ($dateStr == $todayGregorian);
                $dayUsers = isset($shiftsByDay->{$day}) ? $shiftsByDay->{$day} : new stdClass();
                $scheduledGuard = isset($guardSchedule[$day]) ? $guardSchedule[$day] : null;
                $hasCheckin = !empty(get_object_vars($dayUsers));
                $userCount = count(get_object_vars($dayUsers));

                $dbScheduleVars = get_object_vars($dbSchedule);
                $isFromDb = isset($dbSchedule->{$day});

                $primaryUser = null;
                $secondaryUsers = [];

                if ($hasCheckin && $scheduledGuard) {
                    foreach (get_object_vars($dayUsers) as $userId => $userData) {
                        if ($userData->user->getId() == $scheduledGuard->getId()) {
                            $primaryUser = $userData;
                        } else {
                            $secondaryUsers[] = $userData;
                        }
                    }
                } else {
                    $first = true;
                    foreach (get_object_vars($dayUsers) as $userId => $userData) {
                        if ($first) {
                            $primaryUser = $userData;
                            $first = false;
                        } else {
                            $secondaryUsers[] = $userData;
                        }
                    }
                }

                $isIncomplete = false;
                if ($primaryUser) {
                    $totalWorked = 0;
                    foreach ($primaryUser->shifts as $shift) {
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
                            <div class="scheduled-guard">
                                <?php echo htmlspecialchars($scheduledGuard->getName()); ?>
                                <?php echo $isFromDb ? '(ذخیره)' : '(محاسبه)'; ?>
                            </div>
                        <?php elseif ($primaryUser): ?>

                            <?php
                            $user = $primaryUser->user;
                            $userShifts = $primaryUser->shifts;

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

                                $segment = new stdClass();
                                $segment->type = 'work';
                                $segment->start = $sMinutes;
                                $segment->end = $eMinutes;
                                $segment->duration = $duration;
                                $segments[] = $segment;

                                if ($i < count($userShifts) - 1) {
                                    $nextShift = $userShifts[$i + 1];
                                    $nextStartTime = strtotime($nextShift->getStartTime());
                                    $nextSMinutes = (int)date('H', $nextStartTime) * 60 + (int)date('i', $nextStartTime);

                                    if (date('Y-m-d', $nextStartTime) > date('Y-m-d', $sTime)) {
                                        $nextSMinutes += 24 * 60;
                                    }

                                    if ($nextSMinutes > $eMinutes) {
                                        $gapSegment = new stdClass();
                                        $gapSegment->type = 'gap';
                                        $gapSegment->start = $eMinutes;
                                        $gapSegment->end = $nextSMinutes;
                                        $gapSegment->duration = $nextSMinutes - $eMinutes;
                                        $segments[] = $gapSegment;
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
                                if ($seg->type == 'gap') {
                                    $totalGapMinutes += $seg->duration;
                                }
                            }

                            $extraAfterMinutes = 0;
                            if ($totalWorkedMinutes > $standardShiftMinutes) {
                                $extraAfterMinutes = $totalWorkedMinutes - $standardShiftMinutes;
                            }

                            $netExtraMinutes = $extraAfterMinutes - $lateMinutes;

                            $tooltip = "<strong>" . htmlspecialchars($user->getName()) . "</strong> (شیفت اصلی)<br>";
                            $tooltip .= "ورود: " . $firstStartStr . " | خروج: " . $lastEndStr;

                            if ($totalGapMinutes > 0) {
                                $tooltip .= "<br>غیبت: " . TimeFormatter::minutesToTime((int)$totalGapMinutes);
                            }

                            $tooltip .= "<br>";

                            if ($lateMinutes > 0) {
                                $tooltip .= "تاخیر: " . TimeFormatter::minutesToTime($lateMinutes) . " | ";
                            }
                            if ($earlyMinutes > 0) {
                                $tooltip .= "زودتر: " . TimeFormatter::minutesToTime($earlyMinutes) . " | ";
                            }

                            if ($netExtraMinutes != 0 || $lateMinutes > 0 || $extraAfterMinutes > 0) {
                                $tooltip .= "<br><strong>جمع تاخیر/اضافه: ";
                                if ($netExtraMinutes > 0) {
                                    $tooltip .= "<span style='color:#27ae60;'>+" . TimeFormatter::formatDuration((int)$netExtraMinutes) . " اضافه کاری</span>";
                                } elseif ($netExtraMinutes < 0) {
                                    $tooltip .= "<span style='color:#e74c3c;'>" . TimeFormatter::formatDuration((int)abs($netExtraMinutes)) . " تاخیر</span>";
                                } else {
                                    $tooltip .= "<span style='color:#3498db;'>0 (تاخیر و اضافه مساوی)</span>";
                                }
                                $tooltip .= "</strong>";
                            }

                            $tooltip .= "<br>";

                            if ($totalWorkedMinutes >= $standardShiftMinutes) {
                                $tooltip .= "24 ساعت";
                                if ($extraAfterMinutes > 0) {
                                    $tooltip .= " + " . TimeFormatter::formatDuration((int)$extraAfterMinutes) . " اضافه";
                                }
                                $tooltip .= " ✓";
                            } else {
                                $shortage = $standardShiftMinutes - $totalWorkedMinutes;
                                $tooltip .= "<span style='color:#ff6b6b;'>⚠️ ناقص: " . TimeFormatter::formatDuration((int)$shortage) . "</span>";
                            }

                            if ($isActive) {
                                $tooltip .= "<br>⚡ فعال";
                            }

                            $dayMinutes = 24 * 60;
                            ?>

                            <div class="user-shift-container">

                                <?php if ($earlyMinutes > 0):
                                    $width = ($earlyMinutes / $dayMinutes) * 100;
                                    $earlyRight = 0;
                                    $width = min($width, 100);
                                ?>
                                    <div class="shift-block" style="right: <?php echo $earlyRight; ?>%; width: <?php echo $width; ?>%; background: #3498db; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>">
                                        <?php if ($width > 8): ?>
                                            <small><?php echo TimeFormatter::minutesToTime($earlyMinutes); ?> زودتر</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($lateMinutes > 0):
                                    $width = ($lateMinutes / $dayMinutes) * 100;
                                    $lateRight = (($shiftStartMinutes - 360) / $dayMinutes) * 100;
                                    $width = min($width, 100 - $lateRight);
                                ?>
                                    <div class="shift-block" style="right: <?php echo max(0, $lateRight); ?>%; width: <?php echo $width; ?>%; background: #e74c3c; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>">
                                        <?php if ($width > 8): ?>
                                            <small><?php echo TimeFormatter::minutesToTime($lateMinutes); ?> تاخیر</small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach ($segments as $segment):
                                    $segWidth = ($segment->duration / $dayMinutes) * 100;
                                    $segRight = (($segment->start - 360) / $dayMinutes) * 100;

                                    if ($segRight < 0) {
                                        $segWidth += $segRight;
                                        $segRight = 0;
                                    }

                                    if ($segRight + $segWidth > 100) {
                                        $segWidth = 100 - $segRight;
                                    }

                                    $segWidth = max($segWidth, 0.5);

                                    if ($segment->type == 'work'):
                                        $workedBefore = 0;
                                        foreach ($segments as $prev) {
                                            if ($prev === $segment) break;
                                            if ($prev->type == 'work') {
                                                $workedBefore += $prev->duration;
                                            }
                                        }

                                        if ($workedBefore >= $standardShiftMinutes):
                                ?>
                                            <div class="shift-block" style="right: <?php echo $segRight; ?>%; width: <?php echo $segWidth; ?>%; background: #00ccff; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>">
                                                <?php if ($segWidth > 8): ?>
                                                    <small>+<?php echo TimeFormatter::minutesToTime((int)$segment->duration); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($workedBefore + $segment->duration > $standardShiftMinutes):
                                            $mainPart = $standardShiftMinutes - $workedBefore;
                                            $extraPart = $segment->duration - $mainPart;
                                            $mainWidth = ($mainPart / $dayMinutes) * 100;
                                            $extraWidth = ($extraPart / $dayMinutes) * 100;

                                            $mainRight = $segRight;
                                            $extraRight = $segRight + (($mainPart / $segment->duration) * $segWidth);

                                            $mainWidth = min($mainWidth, 100 - $mainRight);
                                            $extraRight = min($extraRight, 100 - $extraWidth);
                                        ?>
                                            <div class="shift-block" style="right: <?php echo $mainRight; ?>%; width: <?php echo $mainWidth; ?>%; background: #27ae60; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                            <div class="shift-block" style="right: <?php echo $extraRight; ?>%; width: <?php echo $extraWidth; ?>%; background: #00aeff; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>">
                                                <?php if ($extraWidth > 8): ?>
                                                    <small>+<?php echo TimeFormatter::minutesToTime((int)$extraPart); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="shift-block" style="right: <?php echo $segRight; ?>%; width: <?php echo $segWidth; ?>%; background: #27ae60; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                        <?php endif;
                                    else: ?>
                                        <div class="shift-block gap-block" style="right: <?php echo $segRight; ?>%; width: <?php echo max($segWidth, 1); ?>%; height: 100%;" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>"></div>
                                <?php endif;
                                endforeach;
                                ?>

                                <div class="user-name-overlay" data-tooltip="<?php echo htmlspecialchars($tooltip); ?>">
                                    <?php echo htmlspecialchars($user->getName()); ?>
                                </div>

                            </div>

                            <?php
                            $secondaryIndex = 0;
                            foreach ($secondaryUsers as $secUserData):
                                $secUser = $secUserData->user;
                                $secShifts = $secUserData->shifts;

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

                                $secRight = (($secStartMinutes - 360) / $dayMinutes) * 100;
                                $secWidth = (($secEndMinutes - $secStartMinutes) / $dayMinutes) * 100;

                                if ($secRight < 0) {
                                    $secWidth += $secRight;
                                    $secRight = 0;
                                }

                                if ($secRight + $secWidth > 100) {
                                    $secWidth = 100 - $secRight;
                                }
                                $secWidth = max($secWidth, 1);

                                $secTooltip = "<strong>" . htmlspecialchars($secUser->getName()) . "</strong> (اضافه کار)<br>";
                                $secTooltip .= "ورود: " . $secStartStr . " | خروج: " . $secEndStr . "<br>";
                                $secTooltip .= "مدت: " . TimeFormatter::formatDuration((int)$secDuration);
                                if ($secIsActive) {
                                    $secTooltip .= "<br>⚡ فعال";
                                }

                                $bottomPosition = 6 + ($secondaryIndex * 26);
                            ?>

                                <div class="secondary-shift-container" data-tooltip="<?php echo htmlspecialchars($secTooltip); ?>"
                                    style="bottom: <?php echo $bottomPosition; ?>px;">
                                    <div class="secondary-shift-block <?php echo $secIsActive ? 'secondary-active' : ''; ?>"
                                        style="right: <?php echo max(0, $secRight); ?>%; width: <?php echo $secWidth; ?>%;">
                                        <?php echo htmlspecialchars($secUser->getName()); ?> (<?php echo $secStartStr; ?>-<?php echo $secEndStr; ?>)
                                    </div>
                                </div>

                            <?php
                                $secondaryIndex++;
                            endforeach;
                            ?>

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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.day-row').forEach(row => {
                const secondaryCount = row.querySelectorAll('.secondary-shift-container').length;
                if (secondaryCount > 0) {
                    const baseHeight = 100;
                    const extraHeight = secondaryCount * 28 + 10;
                    row.style.minHeight = (baseHeight + extraHeight) + 'px';
                    row.style.height = 'auto';
                }
            });
        });
    </script>

<!-- اسکرول به تاریخ روز -->
 
    <!-- <script>
        document.addEventListener('DOMContentLoaded', function() {
            const todayRow = document.querySelector('.day-row.today');

            if (todayRow) {
                todayRow.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });

                const chartBox = todayRow.querySelector('.chart-box');
                if (chartBox) {
                    chartBox.classList.add('box-highlight');

                    const shiftBlocks = chartBox.querySelectorAll('.shift-block, .user-name-overlay, .secondary-shift-container');
                    shiftBlocks.forEach(block => {
                        block.classList.add('inner-highlight');
                    });
                }

                setTimeout(() => {
                    if (chartBox) {
                        chartBox.classList.remove('box-highlight');

                        const shiftBlocks = chartBox.querySelectorAll('.shift-block, .user-name-overlay, .secondary-shift-container');
                        shiftBlocks.forEach(block => {
                            block.classList.remove('inner-highlight');
                        });
                    }
                }, 2000);
            }
        });
    </script> -->

    <script>
        function updateDate() {
            var year = document.getElementById('yearSelect').value;
            var month = document.getElementById('monthSelect').value;
            document.getElementById('dateInput').value = year + '-' + month;
            document.getElementById('monthForm').submit();
        }
    </script>

</body>

</html>