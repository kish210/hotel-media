-- ============================================================
-- SignageCMS Sample Seed Data
-- ============================================================
USE `signage_cms`;

-- Tenant
INSERT INTO `tenants` (`id`,`slug`,`name`,`plan`,`storage_limit`,`screen_limit`,`is_active`) VALUES
(1,'main','رستوران سینا گروپ','pro',53687091200,50,1),
(2,'demo','Demo Restaurant','basic',5368709120,5,1);

-- Users (password: Admin@123456 bcrypt hash)
INSERT INTO `users` (`id`,`tenant_id`,`name`,`email`,`password`,`role`,`language`,`is_active`) VALUES
(1,1,'مدیر سیستم','admin@signagecms.com','$2y$12$LKBgQQKBT4jz.MvLuWm7X.2Dj1mhlL4ZxPaSfW5jzsFVh/XEDyREO','super_admin','fa',1),
(2,1,'مدیر رستوران','manager@signagecms.com','$2y$12$LKBgQQKBT4jz.MvLuWm7X.2Dj1mhlL4ZxPaSfW5jzsFVh/XEDyREO','manager','fa',1),
(3,1,'ویرایشگر محتوا','editor@signagecms.com','$2y$12$LKBgQQKBT4jz.MvLuWm7X.2Dj1mhlL4ZxPaSfW5jzsFVh/XEDyREO','editor','fa',1),
(4,2,'Demo Admin','demo@signagecms.com','$2y$12$LKBgQQKBT4jz.MvLuWm7X.2Dj1mhlL4ZxPaSfW5jzsFVh/XEDyREO','admin','en',1);

-- Locations
INSERT INTO `locations` (`id`,`tenant_id`,`name`,`address`,`city`,`country`,`timezone`) VALUES
(1,1,'شعبه مرکزی','خیابان ولیعصر، پلاک ۱۰۵','تهران','ایران','Asia/Tehran'),
(2,1,'شعبه شمال','سعادت‌آباد، بلوار اصلی','تهران','ایران','Asia/Tehran'),
(3,1,'شعبه اصفهان','خیابان چهارباغ','اصفهان','ایران','Asia/Tehran');

-- Screens
INSERT INTO `screens` (`id`,`tenant_id`,`location_id`,`name`,`code`,`status`,`is_online`,`orientation`,`resolution`) VALUES
(1,1,1,'صفحه ورودی رستوران','SCR001','active',1,'landscape','1920x1080'),
(2,1,1,'منوی اصلی','SCR002','active',1,'landscape','1920x1080'),
(3,1,1,'پنجره وانت','SCR003','active',0,'portrait','1080x1920'),
(4,1,2,'صفحه شعبه شمال','SCR004','active',1,'landscape','3840x2160'),
(5,1,3,'صفحه اصفهان','SCR005','inactive',0,'landscape','1920x1080');

-- Layouts (JSON zones)
INSERT INTO `layouts` (`id`,`tenant_id`,`created_by`,`name`,`canvas_width`,`canvas_height`,`zones`,`is_template`) VALUES
(1,1,1,'تمام‌صفحه',1920,1080,'[{"id":"main","type":"media","x":0,"y":0,"width":1920,"height":1080,"label":"Main Media"}]',1),
(2,1,1,'منوی ۲ ستونه',1920,1080,'[{"id":"left","type":"media","x":0,"y":0,"width":1280,"height":1080,"label":"Featured"},{"id":"right","type":"menu","x":1280,"y":0,"width":640,"height":1080,"label":"Menu"}]',1),
(3,1,1,'داشبورد چهار‌بخش',1920,1080,'[{"id":"main","type":"media","x":0,"y":0,"width":1440,"height":810,"label":"Main"},{"id":"ticker","type":"ticker","x":0,"y":810,"width":1920,"height":270,"label":"Ticker"},{"id":"clock","type":"clock","x":1440,"y":0,"width":480,"height":270,"label":"Clock"},{"id":"weather","type":"weather","x":1440,"y":270,"width":480,"height":540,"label":"Weather"}]',1);

-- Playlists
INSERT INTO `playlists` (`id`,`tenant_id`,`created_by`,`layout_id`,`name`,`default_duration`,`transition`,`loop`) VALUES
(1,1,1,2,'منوی اصلی رستوران',15,'fade',1),
(2,1,1,1,'تبلیغات پرنده',10,'slide',1),
(3,1,1,3,'داشبورد مرکزی',20,'fade',1);

-- Media
INSERT INTO `media` (`id`,`tenant_id`,`uploaded_by`,`name`,`original_name`,`type`,`mime_type`,`file_path`,`file_size`,`duration`) VALUES
(1,1,1,'بنر اصلی','main_banner.jpg','image','image/jpeg','/uploads/media/main_banner.jpg',524288,10),
(2,1,1,'تبلیغ ویژه','special_offer.mp4','video','video/mp4','/uploads/media/special_offer.mp4',10485760,30),
(3,1,1,'وبسایت رستوران','restaurant_web','url',NULL,NULL,0,60);

-- Playlist Items
INSERT INTO `playlist_items` (`playlist_id`,`media_id`,`sort_order`,`duration`,`zone_id`) VALUES
(1,1,1,10,'left'),(1,2,2,30,'left'),(2,1,1,10,'main'),(2,2,2,30,'main'),(3,1,1,20,'main');

-- Menu Categories
INSERT INTO `menu_categories` (`tenant_id`,`location_id`,`name`,`name_en`,`sort_order`,`color`) VALUES
(1,1,'پیش‌غذا','Appetizers',1,'#FF6B35'),
(1,1,'غذای اصلی','Main Course',2,'#E63946'),
(1,1,'نوشیدنی','Beverages',3,'#457B9D'),
(1,1,'دسر','Desserts',4,'#A8DADC');

-- Menu Items
INSERT INTO `menu_items` (`tenant_id`,`category_id`,`name`,`name_en`,`description`,`price`,`original_price`,`currency`,`is_special`,`is_available`) VALUES
(1,1,'سالاد فصل','Season Salad','ترکیبی از سبزیجات تازه',85000,NULL,'IRR',0,1),
(1,1,'سوپ جو','Barley Soup','سوپ خانگی با جو پرک',65000,NULL,'IRR',0,1),
(1,2,'جوجه کباب','Chicken Kebab','جوجه کباب با کره و زعفران',220000,280000,'IRR',1,1),
(1,2,'چلو ماهیچه','Lamb Shank','ماهیچه بره با برنج باسماتی',350000,NULL,'IRR',0,1),
(1,2,'استیک گوساله','Beef Steak','استیک فیله گوساله ۲۵۰ گرمی',480000,NULL,'IRR',1,1),
(1,3,'دوغ محلی','Doogh',NULL,35000,NULL,'IRR',0,1),
(1,3,'نوشابه','Soft Drink',NULL,25000,NULL,'IRR',0,1),
(1,4,'بستنی زعفرانی','Saffron Ice Cream','بستنی سنتی با زعفران',75000,NULL,'IRR',1,1);

-- Schedules
INSERT INTO `schedules` (`tenant_id`,`screen_id`,`playlist_id`,`name`,`type`,`priority`,`is_active`) VALUES
(1,1,3,'داشبورد همیشگی','always',5,1),
(1,2,1,'منوی اصلی','always',5,1),
(1,NULL,2,'تبلیغات همه صفحات','always',3,1);

-- Notifications
INSERT INTO `notifications` (`tenant_id`,`user_id`,`type`,`title`,`body`,`severity`) VALUES
(1,1,'screen_offline','صفحه آفلاین شد','صفحه "پنجره وانت" آفلاین شده است','warning'),
(1,1,'storage_warning','هشدار فضا','۸۰٪ فضای ذخیره‌سازی استفاده شده','warning');
