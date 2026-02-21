<?php
require_once 'database.php';

class CSRF
{
    public static function generateToken()
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateToken($token)
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

class User
{
    private $id;
    private $username;
    private $role;
    private $full_name;
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    private function hydrate($row)
    {
        $this->id = $row['id'];
        $this->username = $row['username'];
        $this->role = $row['role'];
        $this->full_name = $row['full_name'];
    }

    public function login($username, $password)
    {
        $query = "SELECT * FROM users WHERE username = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $storedPassword = $row['password'];

            if (password_verify($password, $storedPassword)) {
                $this->hydrate($row);
                return $this;
            }

            if ($password === $storedPassword) {
                $this->upgradePassword($row['id'], $password);
                $this->hydrate($row);
                return $this;
            }
        }
        return false;
    }

    private function upgradePassword($userId, $plainPassword)
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $hashedPassword, $userId);
        return $stmt->execute();
    }

    public function changePassword($userId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("si", $hashedPassword, $userId);
        return $stmt->execute();
    }

    public static function getById($id)
    {
        $conn = Database::getConnection();
        $query = "SELECT * FROM users WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = new User();
            $user->hydrate($result->fetch_assoc());
            return $user;
        }
        return null;
    }

    public function getAllGuards()
    {
        $query = "SELECT * FROM users WHERE role='guard' ORDER BY full_name ASC";
        $result = $this->conn->query($query);
        $guards = [];
        while ($row = $result->fetch_assoc()) {
            $user = new User();
            $user->hydrate($row);
            $guards[] = $user;
        }
        return $guards;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getName()
    {
        return $this->full_name;
    }
    public function getRole()
    {
        return $this->role;
    }
}

class Shift
{
    private $id;
    private $startTime;
    private $endTime;
    private $user;
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    private function hydrate($row)
    {
        $this->id = $row['id'];
        $this->startTime = $row['start_time'];
        $this->endTime = $row['end_time'];
        $this->user = User::getById($row['user_id']);
    }

    public function startShift($user_id)
    {
        $query = "INSERT INTO shifts (user_id, start_time) VALUES (?, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }

    public function endShift($user_id)
    {
        $query = "UPDATE shifts SET end_time = NOW() WHERE user_id = ? AND end_time IS NULL ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        return $stmt->execute();
    }

    public function getActiveShift($user_id)
    {
        $query = "SELECT * FROM shifts WHERE user_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $shift = new Shift();
            $shift->hydrate($result->fetch_assoc());
            return $shift;
        }
        return null;
    }

    public function getAllShifts()
    {
        $query = "SELECT * FROM shifts ORDER BY start_time DESC";
        $result = $this->conn->query($query);
        $shifts = [];
        while ($row = $result->fetch_assoc()) {
            $shift = new Shift();
            $shift->hydrate($row);
            $shifts[] = $shift;
        }
        return $shifts;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }
    public function getEndTime()
    {
        return $this->endTime;
    }
    public function isActive()
    {
        return $this->endTime == NULL;
    }
    public function getUser()
    {
        return $this->user;
    }
}

class ShiftSetting
{
    private $conn;
    private $id;
    private $settingName;
    private $startHour;
    private $startMinute;
    private $endHour;
    private $endMinute;
    private $isActive;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    private function hydrate($row)
    {
        $this->id = $row['id'];
        $this->settingName = $row['setting_name'];
        $this->startHour = (int)$row['start_hour'];
        $this->startMinute = (int)$row['start_minute'];
        $this->endHour = (int)$row['end_hour'];
        $this->endMinute = (int)$row['end_minute'];
        $this->isActive = (bool)$row['is_active'];
    }

    public function getActiveSetting()
    {
        $query = "SELECT * FROM shift_settings WHERE is_active = TRUE LIMIT 1";
        $result = $this->conn->query($query);

        if ($result && $result->num_rows > 0) {
            $this->hydrate($result->fetch_assoc());
            return $this;
        }

        $this->startHour = 6;
        $this->startMinute = 0;
        $this->endHour = 6;
        $this->endMinute = 0;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }
    public function getSettingName()
    {
        return $this->settingName;
    }
    public function getStartHour()
    {
        return $this->startHour;
    }
    public function getStartMinute()
    {
        return $this->startMinute;
    }
    public function getEndHour()
    {
        return $this->endHour;
    }
    public function getEndMinute()
    {
        return $this->endMinute;
    }
    public function getIsActive()
    {
        return $this->isActive;
    }

    public function getStartMinutes()
    {
        return ($this->startHour * 60) + $this->startMinute;
    }
    public function getEndMinutes()
    {
        return ($this->endHour * 60) + $this->endMinute;
    }

    public function getStartTimeFormatted()
    {
        return sprintf("%02d:%02d", $this->startHour, $this->startMinute);
    }
    public function getEndTimeFormatted()
    {
        return sprintf("%02d:%02d", $this->endHour, $this->endMinute);
    }
}

class GuardSchedule
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function getMonthSchedule($year, $month)
    {
        $query = "SELECT day, guard_id FROM guard_schedules WHERE year = ? AND month = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();

        $schedules = [];
        while ($row = $result->fetch_assoc()) {
            $schedules[$row['day']] = $row['guard_id'];
        }
        return $schedules;
    }

    public function setSchedule($year, $month, $day, $guard_id)
    {
        $query = "INSERT INTO guard_schedules (year, month, day, guard_id) 
                  VALUES (?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE guard_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iiiii", $year, $month, $day, $guard_id, $guard_id);
        return $stmt->execute();
    }

    public function deleteSchedule($year, $month, $day)
    {
        $query = "DELETE FROM guard_schedules WHERE year = ? AND month = ? AND day = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $year, $month, $day);
        return $stmt->execute();
    }

    public function getGuardSchedule($guard_id, $year, $month)
    {
        $query = "SELECT day FROM guard_schedules WHERE guard_id = ? AND year = ? AND month = ? ORDER BY day";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iii", $guard_id, $year, $month);
        $stmt->execute();
        $result = $stmt->get_result();

        $days = [];
        while ($row = $result->fetch_assoc()) {
            $days[] = $row['day'];
        }
        return $days;
    }
}

// ============================================
// ✅ کلاس کامل و صحیح تاریخ شمسی (جلالی) - نسخه نهایی اصلاح شده
// ============================================
class JalaliDate
{
    // ✅ نام ماه‌های شمسی
    public static $month_names = [
        1 => 'فروردین',
        2 => 'اردیبهشت',
        3 => 'خرداد',
        4 => 'تیر',
        5 => 'مرداد',
        6 => 'شهریور',
        7 => 'مهر',
        8 => 'آبان',
        9 => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند'
    ];

    // ✅ نام روزهای هفته - مطابق PHP date('w'): 0=Sunday تا 6=Saturday
    // ولی ترجمه فارسی: یکشنبه تا شنبه
    public static $weekdays = [
        0 => 'یکشنبه',    // Sunday
        1 => 'دوشنبه',    // Monday
        2 => 'سه‌شنبه',   // Tuesday
        3 => 'چهارشنبه',  // Wednesday
        4 => 'پنج‌شنبه',  // Thursday
        5 => 'جمعه',      // Friday
        6 => 'شنبه'       // Saturday
    ];

    /**
     * ✅ تبدیل تاریخ میلادی به شمسی - الگوریتم دقیق
     * 
     * مبدأ دقیق: 1 فروردین سال 1 هجری شمسی = 19 مارس 622 میلادی
     */
    public static function gregorianToJalali($g_y, $g_m, $g_d)
    {
        $gy = (int)$g_y;
        $gm = (int)$g_m;
        $gd = (int)$g_d;

        // ✅ آرایه روزهای ماه میلادی (ایندکس 1-based)
        $g_days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        // ✅ محاسبه سال کبیسه میلادی
        if ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0)) {
            $g_days_in_month[2] = 29;
        }

        // ✅ محاسبه تعداد روز از ابتدای سال میلادی
        $g_day_no = $gd;
        for ($i = 1; $i < $gm; $i++) {
            $g_day_no += $g_days_in_month[$i];
        }

        // ✅ محاسبه تعداد روز از سال 1 میلادی
        $gy2 = $gy - 1;
        $days_from_1_ad = $gy2 * 365 + floor($gy2 / 4) - floor($gy2 / 100) + floor($gy2 / 400);
        $days_from_1_ad += $g_day_no;

        // ✅ اختلاف روزها تا 19 مارس 622 (1 فروردین سال 1)
        // 19 مارس 622 = روز 79 سال 622 میلادی
        // از سال 1 تا 621 = 621 سال
        $days_622_year = 621 * 365 + floor(621 / 4) - floor(621 / 100) + floor(621 / 400);
        $days_622_year += 79; // تا 19 مارس

        $jalali_epoch = $days_622_year; // روزهای تا شروع هجری شمسی

        $jalali_days = $days_from_1_ad - $jalali_epoch;

        // ✅ محاسبه سال شمسی
        $jy = 1;
        while (true) {
            $year_days = self::isKabise($jy) ? 366 : 365;

            if ($jalali_days <= $year_days) {
                break;
            }

            $jalali_days -= $year_days;
            $jy++;
        }

        // ✅ محاسبه ماه شمسی
        $jm = 1;
        while ($jm <= 12) {
            $month_days = self::getMonthDays($jy, $jm);

            if ($jalali_days <= $month_days) {
                break;
            }

            $jalali_days -= $month_days;
            $jm++;
        }

        // ✅ روز شمسی
        $jd = (int)$jalali_days;

        return [
            'year' => $jy,
            'month' => $jm,
            'day' => $jd
        ];
    }

    /**
     * ✅ تبدیل تاریخ شمسی به میلادی - الگوریتم دقیق
     */
    public static function jalaliToGregorian($jy, $jm, $jd)
    {
        $jy = (int)$jy;
        $jm = (int)$jm;
        $jd = (int)$jd;

        // ✅ محاسبه تعداد روز از سال 1 شمسی
        $days = $jd;

        // روزهای ماه‌های کامل قبل
        for ($m = 1; $m < $jm; $m++) {
            $days += self::getMonthDays($jy, $m);
        }

        // روزهای سال‌های کامل قبل
        for ($y = 1; $y < $jy; $y++) {
            $days += self::isKabise($y) ? 366 : 365;
        }

        // ✅ اضافه کردن اختلاف تا سال 1 میلادی (19 مارس 622)
        $days_622_year = 621 * 365 + floor(621 / 4) - floor(621 / 100) + floor(621 / 400);
        $days_622_year += 79;

        $days += $days_622_year;

        // ✅ محاسبه سال میلادی
        $gy = 1;
        while (true) {
            $is_leap = ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0));
            $year_days = $is_leap ? 366 : 365;

            if ($days <= $year_days) {
                break;
            }

            $days -= $year_days;
            $gy++;
        }

        // ✅ محاسبه ماه میلادی
        $g_days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0)) {
            $g_days_in_month[2] = 29;
        }

        $gm = 1;
        while ($gm <= 12) {
            if ($days <= $g_days_in_month[$gm]) {
                break;
            }
            $days -= $g_days_in_month[$gm];
            $gm++;
        }

        $gd = (int)$days;

        return [$gy, $gm, $gd];
    }

    /**
     * ✅ بررسی کبیسه بودن سال شمسی
     * سال کبیسه: باقیمانده تقسیم بر 33 در [1, 5, 9, 13, 17, 22, 26, 30]
     */
    public static function isKabise($year)
    {
        $year = (int)$year;
        $remainder = $year % 33;
        $kabise_reminders = [1, 5, 9, 13, 17, 22, 26, 30];
        return in_array($remainder, $kabise_reminders);
    }

    /**
     * ✅ گرفتن تعداد روزهای یک ماه شمسی
     */
    public static function getMonthDays($year, $month)
    {
        $month = (int)$month;
        $year = (int)$year;

        if ($month >= 1 && $month <= 6) return 31;
        if ($month >= 7 && $month <= 11) return 30;
        if ($month == 12) return self::isKabise($year) ? 30 : 29;
        return 30;
    }

    /**
     * ✅ گرفتن روز هفته - با تبدیل دقیق به timestamp
     */
    public static function getWeekday($year, $month, $day)
    {
        // تبدیل به میلادی
        $gregorian = self::jalaliToGregorian($year, $month, $day);
        $gy = $gregorian[0];
        $gm = $gregorian[1];
        $gd = $gregorian[2];

        // ✅ استفاده از strtotime با فرمت ISO
        $date_string = sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
        $timestamp = strtotime($date_string);

        if ($timestamp === false) {
            // fallback
            $timestamp = mktime(0, 0, 0, $gm, $gd, $gy);
        }

        $w = date('w', $timestamp); // 0=یکشنبه تا 6=شنبه

        return self::$weekdays[$w];
    }

    /**
     * ✅ محاسبه شماره روز در سال شمسی
     */
    public static function getDayOfYear($year, $month, $day)
    {
        $dayOfYear = $day;
        for ($i = 1; $i < $month; $i++) {
            $dayOfYear += self::getMonthDays($year, $i);
        }
        return $dayOfYear;
    }

    /**
     * ✅ گرفتن تاریخ امروز به شمسی
     */
    public static function getToday()
    {
        $gy = (int)date('Y');
        $gm = (int)date('m');
        $gd = (int)date('d');

        return self::gregorianToJalali($gy, $gm, $gd);
    }

    /**
     * ✅ فرمت‌بندی تاریخ شمسی
     */
    public static function format($year, $month, $day, $format = 'Y/m/d')
    {
        $y = (int)$year;
        $m = (int)$month;
        $d = (int)$day;

        $formats = [
            'Y' => $y,
            'y' => substr($y, -2),
            'm' => sprintf('%02d', $m),
            'n' => $m,
            'd' => sprintf('%02d', $d),
            'j' => $d,
            'F' => self::$month_names[$m],
            'M' => substr(self::$month_names[$m], 0, 3),
            'l' => self::getWeekday($y, $m, $d),
            'D' => substr(self::getWeekday($y, $m, $d), 0, 2),
        ];

        $result = $format;
        foreach ($formats as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        return $result;
    }
}
