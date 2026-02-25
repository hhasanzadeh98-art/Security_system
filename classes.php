<?php
require_once 'database.php';

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
        $this->id = $row->id;
        $this->username = $row->username;
        $this->role = $row->role;
        $this->full_name = $row->full_name;
    }

    public function login($username, $password)
    {
        $query = "SELECT * FROM users WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch();

        if ($row) {
            $storedPassword = $row->password;

            if (password_verify($password, $storedPassword)) {
                $this->hydrate($row);
                return $this;
            }

            if ($password === $storedPassword) {
                $this->upgradePassword($row->id, $password);
                $this->hydrate($row);
                return $this;
            }
        }
        return false;
    }

    private function upgradePassword($userId, $plainPassword)
    {
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':password' => $hashedPassword, ':id' => $userId]);
    }

    public function changePassword($userId, $newPassword)
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $query = "UPDATE users SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':password' => $hashedPassword, ':id' => $userId]);
    }

    public static function getById($id)
    {
        $conn = Database::getConnection();
        $query = "SELECT * FROM users WHERE id = :id LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            $user = new User();
            $user->hydrate($row);
            return $user;
        }
        return null;
    }

    public function getAllGuards()
    {
        $query = "SELECT * FROM users WHERE role='guard' ORDER BY full_name ASC";
        $stmt = $this->conn->query($query);
        $guards = [];
        while ($row = $stmt->fetch()) {
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
        $this->id = $row->id;
        $this->startTime = $row->start_time;
        $this->endTime = $row->end_time;
        $this->user = User::getById($row->user_id);
    }

    public function startShift($user_id)
    {
        $query = "INSERT INTO shifts (user_id, start_time) VALUES (:user_id, NOW())";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':user_id' => $user_id]);
    }

    public function endShift($user_id)
    {
        $query = "UPDATE shifts SET end_time = NOW() WHERE user_id = :user_id AND end_time IS NULL ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':user_id' => $user_id]);
    }

    public function getActiveShift($user_id)
    {
        $query = "SELECT * FROM shifts WHERE user_id = :user_id AND end_time IS NULL ORDER BY start_time DESC LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':user_id' => $user_id]);
        $row = $stmt->fetch();

        if ($row) {
            $shift = new Shift();
            $shift->hydrate($row);
            return $shift;
        }
        return null;
    }

    public function getAllShifts()
    {
        $query = "SELECT * FROM shifts ORDER BY start_time DESC";
        $stmt = $this->conn->query($query);
        $shifts = [];
        while ($row = $stmt->fetch()) {
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
        $this->id = $row->id;
        $this->settingName = $row->setting_name;
        $this->startHour = (int)$row->start_hour;
        $this->startMinute = (int)$row->start_minute;
        $this->endHour = (int)$row->end_hour;
        $this->endMinute = (int)$row->end_minute;
        $this->isActive = (bool)$row->is_active;
    }

    public function getActiveSetting()
    {
        $query = "SELECT * FROM shift_settings WHERE is_active = TRUE LIMIT 1";
        $stmt = $this->conn->query($query);
        $row = $stmt->fetch();

        if ($row) {
            $this->hydrate($row);
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
        $query = "SELECT day, guard_id FROM guard_schedules WHERE year = :year AND month = :month";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':year' => $year, ':month' => $month]);

        $schedules = new stdClass();
        while ($row = $stmt->fetch()) {
            $schedules->{$row->day} = $row->guard_id;
        }
        return $schedules;
    }

    public function setSchedule($year, $month, $day, $guard_id)
    {
        $query = "INSERT INTO guard_schedules (year, month, day, guard_id) 
                  VALUES (:year, :month, :day, :guard_id) 
                  ON DUPLICATE KEY UPDATE guard_id = :guard_id2";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':year' => $year,
            ':month' => $month,
            ':day' => $day,
            ':guard_id' => $guard_id,
            ':guard_id2' => $guard_id
        ]);
    }

    public function deleteSchedule($year, $month, $day)
    {
        $query = "DELETE FROM guard_schedules WHERE year = :year AND month = :month AND day = :day";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([':year' => $year, ':month' => $month, ':day' => $day]);
    }

    public function getGuardSchedule($guard_id, $year, $month)
    {
        $query = "SELECT day FROM guard_schedules WHERE guard_id = :guard_id AND year = :year AND month = :month ORDER BY day";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':guard_id' => $guard_id, ':year' => $year, ':month' => $month]);

        $days = [];
        while ($row = $stmt->fetch()) {
            $days[] = $row->day;
        }
        return $days;
    }

    public function getGuardForDate($year, $month, $day)
    {
        $query = "SELECT guard_id FROM guard_schedules 
              WHERE year = :y AND month = :m AND day = :d LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':y' => $year, ':m' => $month, ':d' => $day]);
        $row = $stmt->fetch();
        return $row ? $row->guard_id : null;
    }

    public function getGuardName($guard_id)
    {
        $query = "SELECT full_name FROM users WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $guard_id]);
        $row = $stmt->fetch();
        return $row ? $row->full_name : 'نامشخص';
    }

    public function isGuardOnDutyToday($guardId, $year, $month, $day)
    {
        $query = "SELECT COUNT(*) AS cnt FROM guard_schedules 
              WHERE guard_id = :guard_id 
                AND year = :year 
                AND month = :month 
                AND day = :day 
              LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            ':guard_id' => $guardId,
            ':year'     => $year,
            ':month'    => $month,
            ':day'      => $day
        ]);

        $row = $stmt->fetch();
        return ($row->cnt ?? 0) > 0;
    }
}

class JalaliDate
{
    public static $month_names;
    public static $weekdays;

    public static function init()
    {
        self::$month_names = new stdClass();
        self::$month_names->{1} = 'فروردین';
        self::$month_names->{2} = 'اردیبهشت';
        self::$month_names->{3} = 'خرداد';
        self::$month_names->{4} = 'تیر';
        self::$month_names->{5} = 'مرداد';
        self::$month_names->{6} = 'شهریور';
        self::$month_names->{7} = 'مهر';
        self::$month_names->{8} = 'آبان';
        self::$month_names->{9} = 'آذر';
        self::$month_names->{10} = 'دی';
        self::$month_names->{11} = 'بهمن';
        self::$month_names->{12} = 'اسفند';

        self::$weekdays = new stdClass();
        self::$weekdays->{0} = 'یکشنبه';
        self::$weekdays->{1} = 'دوشنبه';
        self::$weekdays->{2} = 'سه‌شنبه';
        self::$weekdays->{3} = 'چهارشنبه';
        self::$weekdays->{4} = 'پنج‌شنبه';
        self::$weekdays->{5} = 'جمعه';
        self::$weekdays->{6} = 'شنبه';
    }

    public static function gregorianToJalali($g_y, $g_m, $g_d)
    {
        $gy = (int)$g_y;
        $gm = (int)$g_m;
        $gd = (int)$g_d;

        $g_days_in_month = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        if ((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0)) {
            $g_days_in_month[2] = 29;
        }

        $g_day_no = $gd;
        for ($i = 1; $i < $gm; $i++) {
            $g_day_no += $g_days_in_month[$i];
        }

        $gy2 = $gy - 1;
        $days_from_1_ad = $gy2 * 365 + floor($gy2 / 4) - floor($gy2 / 100) + floor($gy2 / 400);
        $days_from_1_ad += $g_day_no;

        $days_622_year = 621 * 365 + floor(621 / 4) - floor(621 / 100) + floor(621 / 400);
        $days_622_year += 79;

        $jalali_epoch = $days_622_year;
        $jalali_days = $days_from_1_ad - $jalali_epoch;

        $jy = 1;
        while (true) {
            $year_days = self::isKabise($jy) ? 366 : 365;

            if ($jalali_days <= $year_days) {
                break;
            }

            $jalali_days -= $year_days;
            $jy++;
        }

        $jm = 1;
        while ($jm <= 12) {
            $month_days = self::getMonthDays($jy, $jm);

            if ($jalali_days <= $month_days) {
                break;
            }

            $jalali_days -= $month_days;
            $jm++;
        }

        $jd = (int)$jalali_days;

        $result = new stdClass();
        $result->year = $jy;
        $result->month = $jm;
        $result->day = $jd;
        return $result;
    }

    public static function jalaliToGregorian($jy, $jm, $jd)
    {
        $jy = (int)$jy;
        $jm = (int)$jm;
        $jd = (int)$jd;

        $days = $jd;

        for ($m = 1; $m < $jm; $m++) {
            $days += self::getMonthDays($jy, $m);
        }

        for ($y = 1; $y < $jy; $y++) {
            $days += self::isKabise($y) ? 366 : 365;
        }

        $days_622_year = 621 * 365 + floor(621 / 4) - floor(621 / 100) + floor(621 / 400);
        $days_622_year += 79;

        $days += $days_622_year;

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

    public static function isKabise($year)
    {
        $year = (int)$year;
        $remainder = $year % 33;
        $kabise_reminders = [1, 5, 9, 13, 17, 22, 26, 30];
        return in_array($remainder, $kabise_reminders);
    }

    public static function getMonthDays($year, $month)
    {
        $month = (int)$month;
        $year = (int)$year;

        if ($month >= 1 && $month <= 6) return 31;
        if ($month >= 7 && $month <= 11) return 30;
        if ($month == 12) return self::isKabise($year) ? 30 : 29;
        return 30;
    }

    public static function getWeekday($year, $month, $day)
    {
        $gregorian = self::jalaliToGregorian($year, $month, $day);
        $gy = $gregorian[0];
        $gm = $gregorian[1];
        $gd = $gregorian[2];

        $date_string = sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
        $timestamp = strtotime($date_string);

        if ($timestamp === false) {
            $timestamp = mktime(0, 0, 0, $gm, $gd, $gy);
        }

        $w = date('w', $timestamp);
        return self::$weekdays->{$w};
    }

    public static function getDayOfYear($year, $month, $day)
    {
        $dayOfYear = $day;
        for ($i = 1; $i < $month; $i++) {
            $dayOfYear += self::getMonthDays($year, $i);
        }
        return $dayOfYear;
    }

    public static function getToday()
    {
        $gy = (int)date('Y');
        $gm = (int)date('m');
        $gd = (int)date('d');

        return self::gregorianToJalali($gy, $gm, $gd);
    }

    public static function format($year, $month, $day, $format = 'Y/m/d')
    {
        $y = (int)$year;
        $m = (int)$month;
        $d = (int)$day;

        $formats = new stdClass();
        $formats->{'Y'} = $y;
        $formats->{'y'} = substr($y, -2);
        $formats->{'m'} = sprintf('%02d', $m);
        $formats->{'n'} = $m;
        $formats->{'d'} = sprintf('%02d', $d);
        $formats->{'j'} = $d;
        $formats->{'F'} = self::$month_names->{$m};
        $formats->{'M'} = substr(self::$month_names->{$m}, 0, 3);
        $formats->{'l'} = self::getWeekday($y, $m, $d);
        $formats->{'D'} = substr(self::getWeekday($y, $m, $d), 0, 2);

        $result = $format;
        foreach (get_object_vars($formats) as $key => $value) {
            $result = str_replace($key, $value, $result);
        }
        return $result;
    }
}

JalaliDate::init();

class TimeFormatter
{
    public static function minutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf("%02d:%02d", $hours, $mins);
    }

    public static function formatDuration($minutes)
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
}

class DateTimeConverter
{
    public static function convertToJalaliDateTime($datetime)
    {
        $timestamp = strtotime($datetime);
        $g_y = date('Y', $timestamp);
        $g_m = date('m', $timestamp);
        $g_d = date('d', $timestamp);
        $time = date('H:i:s', $timestamp);

        $jalali = JalaliDate::gregorianToJalali($g_y, $g_m, $g_d);

        return $jalali->day . ' ' . JalaliDate::$month_names->{$jalali->month} . ' ' . $jalali->year . ' - ' . $time;
    }
}

class Personnel
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function getAllPersonnel()
    {
        $query = "SELECT * FROM personnel WHERE is_active = 1 ORDER BY full_name ASC";
        $stmt = $this->conn->query($query);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getById($id)
    {
        $query = "SELECT * FROM personnel WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }



    public function getNamesByIds($ids)
    {
        if (empty($ids)) return [];

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = "SELECT id, full_name FROM personnel WHERE id IN ($placeholders)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute($ids);

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
            $result[$row->id] = $row->full_name;
        }
        return $result;
    }
}

class GuardShiftReport
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getConnection();
        if (!$this->pdo instanceof PDO) {
            throw new Exception("اتصال به دیتابیس برقرار نشد");
        }
    }

    public function getAllByGuard($guard_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM guard_shift_reports 
            WHERE guard_id = :guard_id 
            ORDER BY report_date DESC
        ");
        $stmt->execute([':guard_id' => $guard_id]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function create(array $data): int|false
    {
        // اول اسم نگهبان رو پیدا می‌کنیم
        $guardName = '';
        if (isset($data['guard_id'])) {
            $userModel = new User();
            $guard = $userModel->getById($data['guard_id']);
            $guardName = $guard ? $guard->getName() : '';
        }

        // اسم نگهبان قبلی رو پیدا می‌کنیم
        $previousGuardName = '';
        if (!empty($data['previous_guard_id'])) {
            $userModel = new User();
            $prevGuard = $userModel->getById($data['previous_guard_id']);
            $previousGuardName = $prevGuard ? $prevGuard->getName() : '';
        }

        // پردازش داده‌های ورود افراد - مهم: داده از people_entries_data میاد
        $peopleEntryType = null;
        $peopleEntryNames = null;
        $peopleEntryTime = null;
        $peopleEntryCount = 0;

        if (!empty($data['people_entries_data'])) {
            $entries = is_string($data['people_entries_data']) ? json_decode($data['people_entries_data'], true) : $data['people_entries_data'];
            if (is_array($entries) && count($entries) > 0) {
                $peopleEntryCount = count($entries);
                // برای سادگی، اولین ورودی رو ذخیره می‌کنیم
                $firstEntry = $entries[0];
                $peopleEntryType = $firstEntry['type'] ?? null;
                $peopleEntryTime = $firstEntry['time'] ?? null;

                if ($peopleEntryType === 'personnel' && !empty($firstEntry['personnel'])) {
                    $names = array_column($firstEntry['personnel'], 'name');
                    $peopleEntryNames = implode('، ', $names);
                } elseif ($peopleEntryType === 'guest' && !empty($firstEntry['guest'])) {
                    $peopleEntryNames = $firstEntry['guest']['name'] ?? '';
                } elseif ($peopleEntryType === 'other' && !empty($firstEntry['other'])) {
                    $peopleEntryNames = $firstEntry['other'];
                }
            }
        } elseif (!empty($data['people_entries'])) {
            // برای سازگاری با کد قبلی
            $entry = $data['people_entries'][0] ?? null;
            if ($entry) {
                $peopleEntryType = $entry['type'] ?? null;
                $peopleEntryTime = $entry['timestamp'] ?? $entry['time'] ?? null;

                if ($peopleEntryType === 'personnel' && !empty($entry['personnel_ids'])) {
                    $names = [];
                    $userModel = new User();
                    foreach ($entry['personnel_ids'] as $id) {
                        $user = $userModel->getById($id);
                        if ($user) {
                            $names[] = $user->getName();
                        }
                    }
                    $peopleEntryNames = implode('، ', $names);
                } elseif ($peopleEntryType === 'guest' && !empty($entry['name'])) {
                    $peopleEntryNames = $entry['name'];
                }
            }
        }

        // استخراج داده‌های خودرو
        $vehiclePlateType = null;
        $vehicleCarType = null;
        $vehicleNote = null;
        $vehicleEntryTime = null;

        if (!empty($data['vehicle_entries'])) {
            $vehicle = $data['vehicle_entries'][0] ?? null;
            if ($vehicle) {
                $vehiclePlateType = $vehicle['plate_type'] ?? null;
                $vehicleCarType = $vehicle['car_type'] ?? null;
                $vehicleNote = $vehicle['note'] ?? null;
                $vehicleEntryTime = $vehicle['timestamp'] ?? $vehicle['time'] ?? null;
            }
        } elseif (!empty($_POST['vehicle_plate_type'])) {
            // اگر از فرم مستقیم اومده
            $vehiclePlateType = $_POST['vehicle_plate_type'] ?? null;
            $vehicleCarType = $_POST['vehicle_car_type'] ?? null;
            $vehicleNote = $_POST['vehicle_note'] ?? null;
            $vehicleEntryTime = $_POST['vehicle_entry_time'] ?? null;
        }

        // استخراج داده‌های اموال
        $propertyType = null;
        $propertyHasNumber = 0;
        $propertyNumber = null;
        $propertyEntryTime = null;

        if (!empty($data['property_entries'])) {
            $property = $data['property_entries'][0] ?? null;
            if ($property) {
                $propertyType = $property['type'] ?? null;
                $propertyHasNumber = isset($property['has_number']) && $property['has_number'] ? 1 : 0;
                $propertyNumber = $property['number'] ?? null;
                $propertyEntryTime = $property['timestamp'] ?? $property['time'] ?? null;
            }
        } elseif (!empty($_POST['property_type'])) {
            $propertyType = $_POST['property_type'] ?? null;
            $propertyHasNumber = isset($_POST['property_has_number']) ? 1 : 0;
            $propertyNumber = $_POST['property_number'] ?? null;
            $propertyEntryTime = $_POST['property_entry_time'] ?? null;
        }

        // استخراج وضعیت روشنایی
        $lightingChecked = isset($data['lighting_checked']) ? (int)$data['lighting_checked'] : 0;
        $lightingComputer = isset($data['lighting_computer']) ? (int)$data['lighting_computer'] : 0;
        $lightingLamp = isset($data['lighting_lamp']) ? (int)$data['lighting_lamp'] : 0;
        $lightingSocket = isset($data['lighting_socket']) ? (int)$data['lighting_socket'] : 0;
        $lightingOther = $data['lighting_other'] ?? null;

        // استخراج وضعیت حفاظتی
        $protectiveChecked = isset($data['protective_checked']) ? (int)$data['protective_checked'] : 0;
        $protectiveShutter = isset($data['protective_shutter']) ? (int)$data['protective_shutter'] : 0;
        $protectiveAlarm = isset($data['protective_alarm']) ? (int)$data['protective_alarm'] : 0;
        $protectiveLock = isset($data['protective_lock']) ? (int)$data['protective_lock'] : 0;
        $protectiveOther = $data['protective_other'] ?? null;

        // استخراج وضعیت سرمایشی/گرمایشی
        $coolingHeatingChecked = isset($data['cooling_heating_checked']) ? (int)$data['cooling_heating_checked'] : 0;
        $coolingKooler = isset($data['cooling_kooler']) ? (int)$data['cooling_kooler'] : 0;
        $heatingHeater = isset($data['heating_heater']) ? (int)$data['heating_heater'] : 0;
        $coolingHeatingOther = $data['cooling_heating_other'] ?? null;

        // استخراج بررسی بخاری و گاز
        $heaterGasChecked = isset($data['heater_gas_checked']) ? (int)$data['heater_gas_checked'] : 0;
        $heaterOff = isset($data['heater_off']) ? (int)$data['heater_off'] : 0;
        $gasLeakCheck = isset($data['gas_leak_check']) ? (int)$data['gas_leak_check'] : 0;
        $gasValveClosed = isset($data['gas_valve_closed']) ? (int)$data['gas_valve_closed'] : 0;
        $heaterGasNote = $data['heater_gas_note'] ?? null;

        // استخراج وضعیت دوربین
        $cameraStatus = $data['camera_status'] ?? null;
        $cameraDetails = $data['camera_details'] ?? null;

        // استخراج گزارش‌های مهم
        $importantIncident = isset($data['important_incident']) ? (int)$data['important_incident'] : 0;
        $importantFailure = isset($data['important_failure']) ? (int)$data['important_failure'] : 0;
        $importantSuspicious = isset($data['important_suspicious']) ? (int)$data['important_suspicious'] : 0;
        $importantFollowup = isset($data['important_followup']) ? (int)$data['important_followup'] : 0;
        $importantOther = $data['important_other'] ?? null;

        $sql = "
        INSERT INTO guard_shift_reports (
            report_date, jalali_year, jalali_month, jalali_day,
            guard_id, guard_name, shift_type, handover_time, previous_guard_id, previous_guard_name,
            appearance, vehicle_control, property_control, camera_monitoring,
            fire_safety, building_check, alarm_system, after_hours_entry,
            forbidden_entry, aquarium_feed, server_room_status, fingerprint, night_rounds,
            
            people_entry_count, people_entry_type, people_entry_names, people_entry_time,
            vehicle_plate_type, vehicle_car_type, vehicle_note, vehicle_entry_time,
            property_type, property_has_number, property_number, property_entry_time,
            
            lighting_checked, lighting_computer, lighting_lamp, lighting_socket, lighting_other,
            protective_checked, protective_shutter, protective_alarm, protective_lock, protective_other,
            cooling_heating_checked, cooling_kooler, heating_heater, cooling_heating_other,
            heater_gas_checked, heater_off, gas_leak_check, gas_valve_closed, heater_gas_note,
            camera_status, camera_details,
            
            night_round1_time, night_round2_time, night_rounds_note,
            server_temp_status, ups_status, fire_alarm_status, server_room_note,
            tasks_performed,
            
            important_incident, important_failure, important_suspicious, important_followup, important_other,
            incidents_text, contacts_text, notes_text,
            handover_signature, received_signature
        ) VALUES (
            :report_date, :jalali_year, :jalali_month, :jalali_day,
            :guard_id, :guard_name, :shift_type, :handover_time, :previous_guard_id, :previous_guard_name,
            :appearance, :vehicle_control, :property_control, :camera_monitoring,
            :fire_safety, :building_check, :alarm_system, :after_hours_entry,
            :forbidden_entry, :aquarium_feed, :server_room_status, :fingerprint, :night_rounds,
            
            :people_entry_count, :people_entry_type, :people_entry_names, :people_entry_time,
            :vehicle_plate_type, :vehicle_car_type, :vehicle_note, :vehicle_entry_time,
            :property_type, :property_has_number, :property_number, :property_entry_time,
            
            :lighting_checked, :lighting_computer, :lighting_lamp, :lighting_socket, :lighting_other,
            :protective_checked, :protective_shutter, :protective_alarm, :protective_lock, :protective_other,
            :cooling_heating_checked, :cooling_kooler, :heating_heater, :cooling_heating_other,
            :heater_gas_checked, :heater_off, :gas_leak_check, :gas_valve_closed, :heater_gas_note,
            :camera_status, :camera_details,
            
            :night_round1_time, :night_round2_time, :night_rounds_note,
            :server_temp_status, :ups_status, :fire_alarm_status, :server_room_note,
            :tasks_performed,
            
            :important_incident, :important_failure, :important_suspicious, :important_followup, :important_other,
            :incidents_text, :contacts_text, :notes_text,
            :handover_signature, :received_signature
        )
    ";

        try {
            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':report_date' => $data['report_date'] ?? null,
                ':jalali_year' => (int)($data['jalali_year'] ?? 0),
                ':jalali_month' => (int)($data['jalali_month'] ?? 0),
                ':jalali_day' => (int)($data['jalali_day'] ?? 0),
                ':guard_id' => (int)($data['guard_id'] ?? 0),
                ':guard_name' => $guardName,
                ':shift_type' => $data['shift_type'] ?? '24h',
                ':handover_time' => $data['handover_time'] ?? null,
                ':previous_guard_id' => $data['previous_guard_id'] ?? null,
                ':previous_guard_name' => $previousGuardName,

                ':appearance' => (int)($data['appearance'] ?? 1),
                ':vehicle_control' => (int)($data['vehicle_control'] ?? 1),
                ':property_control' => (int)($data['property_control'] ?? 1),
                ':camera_monitoring' => (int)($data['camera_monitoring'] ?? 1),
                ':fire_safety' => (int)($data['fire_safety'] ?? 1),
                ':building_check' => (int)($data['building_check'] ?? 1),
                ':alarm_system' => (int)($data['alarm_system'] ?? 1),
                ':after_hours_entry' => (int)($data['after_hours_entry'] ?? 0),
                ':forbidden_entry' => (int)($data['forbidden_entry'] ?? 0),
                ':aquarium_feed' => (int)($data['aquarium_feed'] ?? 0),
                ':server_room_status' => (int)($data['server_room_status'] ?? 1),
                ':fingerprint' => (int)($data['fingerprint'] ?? 1),
                ':night_rounds' => (int)($data['night_rounds'] ?? 1),

                ':people_entry_count' => $peopleEntryCount,
                ':people_entry_type' => $peopleEntryType,
                ':people_entry_names' => $peopleEntryNames,
                ':people_entry_time' => $peopleEntryTime,

                ':vehicle_plate_type' => $vehiclePlateType,
                ':vehicle_car_type' => $vehicleCarType,
                ':vehicle_note' => $vehicleNote,
                ':vehicle_entry_time' => $vehicleEntryTime,

                ':property_type' => $propertyType,
                ':property_has_number' => $propertyHasNumber,
                ':property_number' => $propertyNumber,
                ':property_entry_time' => $propertyEntryTime,

                ':lighting_checked' => $lightingChecked,
                ':lighting_computer' => $lightingComputer,
                ':lighting_lamp' => $lightingLamp,
                ':lighting_socket' => $lightingSocket,
                ':lighting_other' => $lightingOther,

                ':protective_checked' => $protectiveChecked,
                ':protective_shutter' => $protectiveShutter,
                ':protective_alarm' => $protectiveAlarm,
                ':protective_lock' => $protectiveLock,
                ':protective_other' => $protectiveOther,

                ':cooling_heating_checked' => $coolingHeatingChecked,
                ':cooling_kooler' => $coolingKooler,
                ':heating_heater' => $heatingHeater,
                ':cooling_heating_other' => $coolingHeatingOther,

                ':heater_gas_checked' => $heaterGasChecked,
                ':heater_off' => $heaterOff,
                ':gas_leak_check' => $gasLeakCheck,
                ':gas_valve_closed' => $gasValveClosed,
                ':heater_gas_note' => $heaterGasNote,

                ':camera_status' => $cameraStatus,
                ':camera_details' => $cameraDetails,

                ':night_round1_time' => $data['night_round1_time'] ?? null,
                ':night_round2_time' => $data['night_round2_time'] ?? null,
                ':night_rounds_note' => $data['night_rounds_note'] ?? '',

                ':server_temp_status' => $data['server_temp_status'] ?? 'normal',
                ':ups_status' => $data['ups_status'] ?? 'healthy',
                ':fire_alarm_status' => $data['fire_alarm_status'] ?? 'active',
                ':server_room_note' => $data['server_room_note'] ?? '',

                ':tasks_performed' => $data['tasks_performed'] ?? '',

                ':important_incident' => $importantIncident,
                ':important_failure' => $importantFailure,
                ':important_suspicious' => $importantSuspicious,
                ':important_followup' => $importantFollowup,
                ':important_other' => $importantOther,

                ':incidents_text' => $data['incidents_text'] ?? '',
                ':contacts_text' => $data['contacts_text'] ?? '',
                ':notes_text' => $data['notes_text'] ?? '',

                ':handover_signature' => $data['handover_signature'] ?? null,
                ':received_signature' => $data['received_signature'] ?? null,
            ]);

            return (int) $this->pdo->lastInsertId() ?: false;
        } catch (PDOException $e) {
            error_log("GuardShiftReport::create خطا: " . $e->getMessage());
            // اضافه کردن این خط برای نمایش خطا
            echo "<pre style='background:#fee; color:#c00; padding:10px; margin:10px; border:2px solid red;'>";
            echo "خطای دیتابیس: " . $e->getMessage() . "\n";
            echo "کد خطا: " . $e->getCode() . "\n";
            echo "</pre>";
            return false;
        }
    }

    public function update(int $reportId, array $data): bool
    {
        $sql = "
            UPDATE guard_shift_reports SET
                shift_type = :shift_type,
                handover_time = :handover_time,
                previous_guard_id = :previous_guard_id,
                appearance = :appearance,
                vehicle_control = :vehicle_control,
                property_control = :property_control,
                camera_monitoring = :camera_monitoring,
                fire_safety = :fire_safety,
                building_check = :building_check,
                alarm_system = :alarm_system,
                after_hours_entry = :after_hours_entry,
                forbidden_entry = :forbidden_entry,
                aquarium_feed = :aquarium_feed,
                server_room_status = :server_room_status,
                fingerprint = :fingerprint,
                night_rounds = :night_rounds,
                night_round1_time = :night_round1_time,
                night_round2_time = :night_round2_time,
                night_rounds_note = :night_rounds_note,
                server_temp_status = :server_temp_status,
                ups_status = :ups_status,
                fire_alarm_status = :fire_alarm_status,
                server_room_note = :server_room_note,
                tasks_performed = :tasks_performed,
                incidents_text = :incidents_text,
                contacts_text = :contacts_text,
                notes_text = :notes_text,
                handover_signature = :handover_signature,
                received_signature = :received_signature,
                updated_at = NOW()
            WHERE id = :id AND guard_id = :guard_id
        ";

        try {
            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                ':shift_type' => $data['shift_type'] ?? '24h',
                ':handover_time' => $data['handover_time'] ?? null,
                ':previous_guard_id' => $data['previous_guard_id'] ?? null,
                ':appearance' => (int)($data['appearance'] ?? 1),
                ':vehicle_control' => (int)($data['vehicle_control'] ?? 1),
                ':property_control' => (int)($data['property_control'] ?? 1),
                ':camera_monitoring' => (int)($data['camera_monitoring'] ?? 1),
                ':fire_safety' => (int)($data['fire_safety'] ?? 1),
                ':building_check' => (int)($data['building_check'] ?? 1),
                ':alarm_system' => (int)($data['alarm_system'] ?? 1),
                ':after_hours_entry' => (int)($data['after_hours_entry'] ?? 0),
                ':forbidden_entry' => (int)($data['forbidden_entry'] ?? 0),
                ':aquarium_feed' => (int)($data['aquarium_feed'] ?? 0),
                ':server_room_status' => (int)($data['server_room_status'] ?? 1),
                ':fingerprint' => (int)($data['fingerprint'] ?? 1),
                ':night_rounds' => (int)($data['night_rounds'] ?? 1),
                ':night_round1_time' => $data['night_round1_time'] ?? null,
                ':night_round2_time' => $data['night_round2_time'] ?? null,
                ':night_rounds_note' => $data['night_rounds_note'] ?? '',
                ':server_temp_status' => $data['server_temp_status'] ?? 'normal',
                ':ups_status' => $data['ups_status'] ?? 'healthy',
                ':fire_alarm_status' => $data['fire_alarm_status'] ?? 'active',
                ':server_room_note' => $data['server_room_note'] ?? '',
                ':tasks_performed' => $data['tasks_performed'] ?? '',
                ':incidents_text' => $data['incidents_text'] ?? '',
                ':contacts_text' => $data['contacts_text'] ?? '',
                ':notes_text' => $data['notes_text'] ?? '',
                ':handover_signature' => $data['handover_signature'] ?? null,
                ':received_signature' => $data['received_signature'] ?? null,
                ':id' => $reportId,
                ':guard_id' => (int)($data['guard_id'] ?? 0),
            ]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("خطا در update گزارش: " . $e->getMessage());
            return false;
        }
    }

    public function getByDateAndGuard(string $date, int $guardId): ?stdClass
    {
        $sql = "
            SELECT * FROM guard_shift_reports 
            WHERE report_date = :date AND guard_id = :guard_id 
            LIMIT 1
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':date' => $date, ':guard_id' => $guardId]);
            return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
        } catch (PDOException $e) {
            error_log("خطا در getByDateAndGuard: " . $e->getMessage());
            return null;
        }
    }

    public function getMonthReports(int $year, int $month): array
    {
        $sql = "
            SELECT 
                gsr.*,
                u.full_name AS guard_name,
                (gsr.appearance + gsr.vehicle_control + gsr.property_control + gsr.camera_monitoring +
                 gsr.fire_safety + gsr.building_check + gsr.alarm_system + gsr.after_hours_entry +
                 gsr.forbidden_entry + gsr.aquarium_feed + gsr.server_room_status + gsr.fingerprint +
                 gsr.night_rounds) AS checked_count
            FROM guard_shift_reports gsr
            LEFT JOIN users u ON gsr.guard_id = u.id
            WHERE gsr.jalali_year = :year AND gsr.jalali_month = :month
            ORDER BY gsr.report_date ASC
        ";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':year' => $year, ':month' => $month]);
            return $stmt->fetchAll(PDO::FETCH_OBJ) ?: [];
        } catch (PDOException $e) {
            error_log("خطا در getMonthReports: " . $e->getMessage());
            return [];
        }
    }

    public function delete(int $reportId, int $guardId): bool
    {
        $sql = "DELETE FROM guard_shift_reports WHERE id = :id AND guard_id = :guard_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $reportId, ':guard_id' => $guardId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("خطا در delete گزارش: " . $e->getMessage());
            return false;
        }
    }

    private function encodeJson($data): string
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }
}
