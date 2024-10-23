# ระบบค้นหาไฟล์ (Secure File Search)

ระบบจัดการและค้นหาไฟล์ที่มีความปลอดภัยสูง พัฒนาด้วย PHP มาพร้อมกับฟีเจอร์การค้นหาแบบเรียลไทม์และระบบรักษาความปลอดภัยที่ครบครัน

## ผู้พัฒนา

- **ผู้แต่ง:** Goragod Wiriya
- **เว็บไซต์:** https://kotchasan.com
- **GitHub:** https://github.com/goragodwiriya/filesearch

## 🔐 คุณสมบัติหลัก

- ระบบยืนยันตัวตนที่ปลอดภัย
- ค้นหาไฟล์และเนื้อหาแบบเรียลไทม์
- ระบบดูและลบไฟล์
- ป้องกัน CSRF
- จัดการ Session
- จำกัดการเรียกใช้งาน (Rate Limiting)
- ระบบออกจากระบบที่ปลอดภัย
- บันทึกข้อผิดพลาดอย่างครบถ้วน
- ป้องกัน XSS
- ตรวจสอบข้อมูลนำเข้า

## 🚀 ความต้องการของระบบ

- PHP 7.4 ขึ้นไป
- เว็บเซิร์ฟเวอร์ (Apache/Nginx)
- เว็บเบราว์เซอร์รุ่นใหม่
- เปิดใช้งาน Session ใน PHP
- สิทธิ์ในการอ่าน/เขียนไฟล์

## 📦 การติดตั้ง

1. Clone โปรเจค:
```bash
git clone https://github.com/goragodwiriya/filesearch.git
```

2. ตั้งค่าเว็บเซิร์ฟเวอร์ให้ชี้ไปที่โฟลเดอร์โปรเจค

3. กำหนดสิทธิ์การเข้าถึงไฟล์:
```bash
chmod 755 ./
chmod 644 *.php
chmod 755 logs/
chmod 644 logs/*.log
```

4. สร้างรหัสผ่านด้วย `generate_password_hash.php`

5. อัพเดทข้อมูลผู้ใช้ใน `login.php`

## 🔧 การตั้งค่า

### การตั้งค่า ROOT_DIR

ROOT_DIR คือการกำหนดโฟลเดอร์หลักที่ระบบจะใช้ในการค้นหาไฟล์ แก้ไขได้ในไฟล์ `file_manager_ajax.php`

#### วิธีการตั้งค่าพื้นฐาน:

```php
// ค้นหาในโฟลเดอร์เดียวกับสคริปต์
define('ROOT_DIR', __DIR__.'/');

// ค้นหาในโฟลเดอร์ย่อย documents
define('ROOT_DIR', __DIR__.'/documents/');

// ค้นหาในโฟลเดอร์แม่
define('ROOT_DIR', dirname(__DIR__).'/');
```

#### การตั้งค่าแบบระบุเส้นทางเต็ม:

```php
// Windows
define('ROOT_DIR', 'C:/xampp/htdocs/myfiles/');

// Linux/Unix
define('ROOT_DIR', '/var/www/html/myfiles/');
```

#### การตั้งค่าที่แนะนำ:

```php
// ตั้งค่าพื้นฐานที่ปลอดภัย
define('ROOT_DIR', realpath(__DIR__.'/documents/').DIRECTORY_SEPARATOR);

// ตรวจสอบและสร้างไดเรกทอรีถ้ายังไม่มี
if (!is_dir(ROOT_DIR)) {
    mkdir(ROOT_DIR, 0755, true);
}

// กำหนดไดเรกทอรีที่ไม่อนุญาต
define('EXCLUDED_DIRS', [
    'node_modules',
    '.git',
    'vendor',
    '.svn',
    'system',
    'config'
]);
```

#### ข้อควรระวัง:
- ต้องกำหนดสิทธิ์การเข้าถึงที่เหมาะสม
- หลีกเลี่ยงการชี้ไปยังโฟลเดอร์ระบบหรือโฟลเดอร์ที่มีข้อมูลสำคัญ
- ควรใช้ DIRECTORY_SEPARATOR แทนการใช้ / หรือ \ โดยตรง
- ตรวจสอบให้แน่ใจว่าเส้นทางลงท้ายด้วย / หรือ DIRECTORY_SEPARATOR

### ข้อมูลเข้าสู่ระบบเริ่มต้น
- ชื่อผู้ใช้: admin
- รหัสผ่าน: 1234!

วิธีเพิ่มหรือแก้ไขผู้ใช้ ให้แก้ไขอาร์เรย์ `$users` ใน `login.php`:
```php
$users = [
    'admin' => '$2y$10$PFA9E.o45g7B74arZ4TkRORkk1y5Oxqb3IqMSGAGurM8JLXK/fKA6' // รหัสผ่าน: 1234
    // เพิ่มผู้ใช้เพิ่มเติมที่นี่
];
```

### การตั้งค่าความปลอดภัย
ค่าความปลอดภัยหลักอยู่ใน `file_manager_ajax.php`:
```php
define('MAX_FILESIZE', 10 * 1024 * 1024); // ขนาดไฟล์สูงสุด 10MB
define('RATE_LIMIT', 100); // จำนวนคำขอสูงสุดต่อผู้ใช้
define('RATE_LIMIT_WINDOW', 3600); // ระยะเวลาในการจำกัด (วินาที)
```

## 🛡️ ระบบความปลอดภัย

- เข้ารหัสรหัสผ่านด้วย `password_hash()` และ `password_verify()`
- ป้องกัน CSRF ด้วยระบบ Token
- ยืนยันตัวตนด้วย Session
- จำกัดการเรียกใช้งาน
- ตรวจสอบและทำความสะอาดข้อมูลนำเข้า
- ตรวจสอบเส้นทางไฟล์อย่างปลอดภัย
- ป้องกันการเข้าถึงไดเรกทอรี
- ใช้ Content-Security-Policy headers
- ป้องกัน XSS
- ระบบออกจากระบบที่ปลอดภัย

## 📁 โครงสร้างโปรเจค

```
secure-file-search/
├── assets/
│   └── styles.css
├── logs/
│   ├── access.log
│   └── error.log
├── index.php
├── login.php
├── logout.php
├── file_manager.php
├── file_manager_ajax.php
├── generate_password_hash.php
└── README.md
```

## 🚨 ข้อควรพิจารณาด้านความปลอดภัย

1. อัพเดท PHP และเว็บเซิร์ฟเวอร์อยู่เสมอ
2. ตั้งค่าสิทธิ์การเข้าถึงไฟล์อย่างเหมาะสม
3. ใช้ HTTPS ในการใช้งานจริง
4. หมุนเวียนไฟล์ล็อกอย่างสม่ำเสมอ
5. ตรวจสอบล็อกการเข้าถึงเพื่อหากิจกรรมที่น่าสงสัย
6. เปลี่ยนรหัสผ่านผู้ใช้เป็นประจำ
7. สำรองข้อมูลไฟล์ที่ถูกลบอย่างปลอดภัย

## 📄 ลิขสิทธิ์

โปรเจคนี้อยู่ภายใต้ลิขสิทธิ์ MIT License - ดูรายละเอียดเพิ่มเติมได้ที่ไฟล์ LICENSE