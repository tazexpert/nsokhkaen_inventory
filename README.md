# ระบบบริหารจัดการวัสดุและครุภัณฑ์ สำนักงานสถิติจังหวัดขอนแก่น

ระบบเว็บแอปพลิเคชันสำหรับบริหารจัดการวัสดุสิ้นเปลืองและครุภัณฑ์ พร้อมระบบ QR Code สำหรับเบิก/ยืม/คืน

**ไม่ต้องติดตั้งผ่าน Composer** — ไลบรารี PHP ทั้งหมดถูกดาวน์โหลดมาเก็บไว้ในโฟลเดอร์ `libs/` ของโปรเจกต์เรียบร้อยแล้ว เพียง `git pull`, รันไฟล์ SQL และแก้ค่า config ฐานข้อมูลก็ใช้งานได้ทันที

## เทคโนโลยีที่ใช้

- PHP 8+ (PDO)
- MySQL
- Bootstrap 5, jQuery (โหลดจาก CDN)
- `libs/phpqrcode/` — ไลบรารี [PHP QR Code](https://github.com/t0k4rt/phpqrcode) (ไฟล์ PHP ล้วน ไม่มี dependency ภายนอก) สำหรับสร้าง QR Code
- `libs/simplexlsx/` — ไลบรารี [SimpleXLSX](https://github.com/shuchkin/simplexlsx) / [SimpleXLSXGen](https://github.com/shuchkin/simplexlsxgen) (ไฟล์คลาสเดี่ยว ไม่มี dependency ภายนอก) สำหรับอ่าน/เขียนไฟล์ Excel (.xlsx)
- [html5-qrcode](https://github.com/mebjas/html5-qrcode) สำหรับสแกน QR Code ผ่านกล้อง (โหลดจาก CDN ฝั่ง JavaScript)

> ไลบรารีทั้งสองตัวใช้เฉพาะส่วนขยายมาตรฐานของ PHP (เช่น `gd`, `zip`, `simplexml`) ซึ่งมักเปิดใช้งานอยู่แล้วในโฮสติ้ง PHP ทั่วไป ไม่ต้องรัน `composer install`

## การติดตั้ง

1. `git pull` หรือ clone โปรเจกต์นี้ลงเครื่อง/เซิร์ฟเวอร์ (ไม่ต้องรันคำสั่งติดตั้งไลบรารีใดๆ เพิ่มเติม)

2. สร้างฐานข้อมูลและตารางทั้งหมดโดยรันไฟล์ `database/schema.sql` (มีข้อมูลจำลองรวมอยู่ด้วย):
   ```
   mysql -u root -p < database/schema.sql
   ```

3. แก้ไขค่าการเชื่อมต่อฐานข้อมูลในไฟล์ `config/database.php` (หรือกำหนดผ่าน environment variables):
   ```
   DB_HOST=localhost
   DB_NAME=nsokhkaen_inventory
   DB_USER=root
   DB_PASS=yourpassword
   ```

4. ตั้งค่า document root ของเว็บเซิร์ฟเวอร์ให้ชี้มาที่โฟลเดอร์โปรเจกต์ แล้วเปิด `public/index.php`

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
├── libs/             # ไลบรารี PHP ที่ดาวน์โหลดมาเก็บไว้ล่วงหน้า (phpqrcode, simplexlsx) - ไม่ต้อง composer install
├── public/           # หน้าเว็บที่เข้าถึงได้โดยตรง (document root)
└── uploads/          # โฟลเดอร์ชั่วคราวสำหรับไฟล์ที่อัปโหลด
```

## สิทธิ์การใช้งาน (Role)

- **Admin**: จัดการวัสดุ/ครุภัณฑ์ (CRUD), นำเข้า Excel (.xlsx), พิมพ์ QR Code, ดูรายงาน, สแกนเบิก/ยืม/คืน
- **Staff**: สแกนเบิก/ยืม/คืนผ่านหน้า "สแกน QR Code" เท่านั้น

## หมายเหตุการนำเข้าไฟล์ Excel

รองรับเฉพาะไฟล์ `.xlsx` (Excel 2007 ขึ้นไป) เท่านั้น หากมีไฟล์ `.xls` หรือ `.csv` เก่า ให้เปิดด้วย Excel/LibreOffice แล้ว "Save As" เป็น `.xlsx` ก่อนนำเข้า
