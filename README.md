# ระบบบริหารจัดการวัสดุและครุภัณฑ์ สำนักงานสถิติจังหวัดขอนแก่น

ระบบเว็บแอปพลิเคชันสำหรับบริหารจัดการวัสดุสิ้นเปลืองและครุภัณฑ์ พร้อมระบบ QR Code สำหรับเบิก/ยืม/คืน

## เทคโนโลยีที่ใช้

- PHP 8+ (PDO)
- MySQL
- Bootstrap 5, jQuery
- [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) สำหรับนำเข้า/ส่งออก Excel
- [endroid/qr-code](https://github.com/endroid/qr-code) สำหรับสร้าง QR Code (ทดแทน phpqrcode ที่เลิกดูแลแล้ว)
- [html5-qrcode](https://github.com/mebjas/html5-qrcode) สำหรับสแกน QR Code ผ่านกล้อง (โหลดจาก CDN)

## การติดตั้ง

1. ติดตั้ง dependency ด้วย Composer:
   ```
   composer install
   ```

2. สร้างฐานข้อมูลและตารางทั้งหมดโดยรันไฟล์ `database/schema.sql` (มีข้อมูลจำลองรวมอยู่ด้วย):
   ```
   mysql -u root -p < database/schema.sql
   ```

3. ตั้งค่าการเชื่อมต่อฐานข้อมูลผ่าน environment variables (หรือแก้ค่า default ใน `config/database.php`):
   ```
   DB_HOST=localhost
   DB_NAME=nsokhkaen_inventory
   DB_USER=root
   DB_PASS=yourpassword
   ```

4. ตั้งค่า document root ให้ชี้มาที่โฟลเดอร์โปรเจกต์ แล้วเปิด `public/index.php`

5. บัญชีผู้ใช้เริ่มต้น (รหัสผ่าน `password123` ทั้งสองบัญชี):
   - `admin` — สิทธิ์ผู้ดูแลระบบ (จัดการได้ทุกระบบ)
   - `staff1` — สิทธิ์เจ้าหน้าที่ (สแกน เบิก ยืม คืน เท่านั้น)

## โครงสร้างโปรเจกต์

```
├── api/              # AJAX backend endpoints (materials, assets, scan, report, import)
├── assets/           # CSS / JS ฝั่งหน้าเว็บ
├── config/           # การเชื่อมต่อฐานข้อมูลและค่าตั้งต้นของระบบ
├── database/         # schema.sql (โครงสร้างตาราง + ข้อมูลจำลอง)
├── includes/         # ฟังก์ชันกลาง, การตรวจสอบสิทธิ์, header/footer
├── public/           # หน้าเว็บที่เข้าถึงได้โดยตรง (document root)
├── uploads/          # โฟลเดอร์ชั่วคราวสำหรับไฟล์ที่อัปโหลด
└── vendor/           # Composer dependencies (สร้างหลัง composer install)
```

## สิทธิ์การใช้งาน (Role)

- **Admin**: จัดการวัสดุ/ครุภัณฑ์ (CRUD), นำเข้า Excel, พิมพ์ QR Code, ดูรายงาน, สแกนเบิก/ยืม/คืน
- **Staff**: สแกนเบิก/ยืม/คืนผ่านหน้า "สแกน QR Code" เท่านั้น
