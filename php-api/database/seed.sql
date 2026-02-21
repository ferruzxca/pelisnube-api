SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE rate_limits;
TRUNCATE TABLE subscription_history;
TRUNCATE TABLE plan_history;
TRUNCATE TABLE section_history;
TRUNCATE TABLE content_history;
TRUNCATE TABLE user_history;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE password_otps;
TRUNCATE TABLE payment_attempts;
TRUNCATE TABLE favorites;
TRUNCATE TABLE content_sections;
TRUNCATE TABLE subscriptions;
TRUNCATE TABLE subscription_plans;
TRUNCATE TABLE sections;
TRUNCATE TABLE contents;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO users (id, name, email, password_hash, role, status, is_active, preferred_lang, must_change_password, created_at, updated_at)
VALUES
('00000000-0000-0000-0000-000000000001', 'Super Admin', 'superadmin@pelisnube.local', '$2y$12$9dt7eri.zlRhH4jd0DhgWeRG/eAptqLM8/Yhhwhu76DW5g49wuHb.', 'SUPER_ADMIN', 'ACTIVE', 1, 'es', 1, NOW(), NOW()),
('00000000-0000-0000-0000-000000000002', 'Admin Operativo', 'admin@pelisnube.local', '$2y$12$9dt7eri.zlRhH4jd0DhgWeRG/eAptqLM8/Yhhwhu76DW5g49wuHb.', 'ADMIN', 'ACTIVE', 1, 'es', 0, NOW(), NOW()),
('00000000-0000-0000-0000-000000000003', 'Usuario Demo', 'user@pelisnube.local', '$2y$12$VkRc1wPdiy1pnL8LMkeBTOPeoG5iE5oCqj7s9.iz5L6T3XwR8a.Ke', 'USER', 'ACTIVE', 1, 'es', 0, NOW(), NOW());

INSERT INTO subscription_plans (id, code, name, price_monthly, currency, quality, screens, is_active, created_at, updated_at)
VALUES
('20000000-0000-0000-0000-000000000001', 'BASIC', 'Basic', 119.00, 'MXN', 'HD', 1, 1, NOW(), NOW()),
('20000000-0000-0000-0000-000000000002', 'STANDARD', 'Standard', 189.00, 'MXN', 'Full HD', 2, 1, NOW(), NOW()),
('20000000-0000-0000-0000-000000000003', 'PREMIUM', 'Premium', 259.00, 'MXN', '4K + HDR', 4, 1, NOW(), NOW());

INSERT INTO subscriptions (id, user_id, plan_id, status, started_at, renewal_at, ended_at, is_active, created_at, updated_at)
VALUES
('30000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000003', '20000000-0000-0000-0000-000000000002', 'ACTIVE', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, 1, NOW(), NOW());

INSERT INTO sections (id, section_key, name, description, sort_order, is_home_visible, is_active, created_at, updated_at)
VALUES
('10000000-0000-0000-0000-000000000001', 'trending-now', 'Tendencias ahora', 'Lo mas visto esta semana.', 1, 1, 1, NOW(), NOW()),
('10000000-0000-0000-0000-000000000002', 'estrenos', 'Estrenos', 'Nuevos lanzamientos.', 2, 1, 1, NOW(), NOW()),
('10000000-0000-0000-0000-000000000003', 'series-estrella', 'Series estrella', 'Series destacadas.', 3, 1, 1, NOW(), NOW()),
('10000000-0000-0000-0000-000000000004', 'sci-fi-night', 'Sci-Fi Night', 'Ciencia ficcion para maratonear.', 4, 1, 1, NOW(), NOW()),
('10000000-0000-0000-0000-000000000005', 'drama-selecto', 'Drama selecto', 'Historias dramaticas y biograficas.', 5, 1, 1, NOW(), NOW()),
('10000000-0000-0000-0000-000000000006', 'accion-max', 'Accion Max', 'Accion, superheroes y adrenalina.', 6, 1, 1, NOW(), NOW());

INSERT INTO payment_attempts (id, user_id, user_email, plan_id, amount, currency, card_last4, card_brand, status, reason, metadata, created_at)
VALUES
('40000000-0000-0000-0000-000000000001', '00000000-0000-0000-0000-000000000003', 'user@pelisnube.local', '20000000-0000-0000-0000-000000000002', 189.00, 'MXN', '4242', 'SIMULATED', 'SUCCESS', NULL, '{"seed":true}', NOW());

INSERT INTO contents (id, title, slug, type, synopsis, year, duration, rating, trailer_watch_url, trailer_embed_url, poster_url, banner_url, is_active, created_at, updated_at) VALUES
('50000000-0000-0000-0000-000000000001','Inception','inception','MOVIE','Dom Cobb roba secretos a traves de los suenos y recibe una mision de implantar una idea.',2010,148,8.8,'https://www.youtube.com/watch?v=YoHD9XEInc0','https://www.youtube.com/embed/YoHD9XEInc0','https://image.tmdb.org/t/p/w780/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg','https://image.tmdb.org/t/p/w1280/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000002','Interstellar','interstellar','MOVIE','Un grupo de astronautas viaja por un agujero de gusano para salvar a la humanidad.',2014,169,8.7,'https://www.youtube.com/watch?v=zSWdZVtXT7E','https://www.youtube.com/embed/zSWdZVtXT7E','https://image.tmdb.org/t/p/w780/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg','https://image.tmdb.org/t/p/w1280/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000003','The Dark Knight','the-dark-knight','MOVIE','Batman enfrenta al Joker en Gotham mientras la ciudad cae en el caos.',2008,152,9.0,'https://www.youtube.com/watch?v=EXeTwQWrcwY','https://www.youtube.com/embed/EXeTwQWrcwY','https://image.tmdb.org/t/p/w780/qJ2tW6WMUDux911r6m7haRef0WH.jpg','https://image.tmdb.org/t/p/w1280/qJ2tW6WMUDux911r6m7haRef0WH.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000004','Dune','dune-2021','MOVIE','Paul Atreides debe proteger Arrakis, el planeta mas peligroso del universo.',2021,155,8.0,'https://www.youtube.com/watch?v=n9xhJrPXop4','https://www.youtube.com/embed/n9xhJrPXop4','https://image.tmdb.org/t/p/w780/d5NXSklXo0qyIYkgV94XAgMIckC.jpg','https://image.tmdb.org/t/p/w1280/d5NXSklXo0qyIYkgV94XAgMIckC.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000005','Oppenheimer','oppenheimer','MOVIE','Biografia de J. Robert Oppenheimer y la creacion de la bomba atomica.',2023,180,8.4,'https://www.youtube.com/watch?v=uYPbbksJxIg','https://www.youtube.com/embed/uYPbbksJxIg','https://image.tmdb.org/t/p/w780/ptpr0kGAckfQkJeJIt8st5dglvd.jpg','https://image.tmdb.org/t/p/w1280/ptpr0kGAckfQkJeJIt8st5dglvd.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000006','Blade Runner 2049','blade-runner-2049','MOVIE','Un nuevo blade runner descubre un secreto que puede desestabilizar la sociedad.',2017,164,8.0,'https://www.youtube.com/watch?v=gCcx85zbxz4','https://www.youtube.com/embed/gCcx85zbxz4','https://image.tmdb.org/t/p/w780/gajva2L0rPYkEWjzgFlBXCAVBE5.jpg','https://image.tmdb.org/t/p/w1280/gajva2L0rPYkEWjzgFlBXCAVBE5.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000007','Stranger Things','stranger-things','SERIES','Un grupo de amigos se enfrenta a fuerzas sobrenaturales en Hawkins.',2016,51,8.7,'https://www.youtube.com/watch?v=b9EkMc79ZSU','https://www.youtube.com/embed/b9EkMc79ZSU','https://image.tmdb.org/t/p/w780/uOOtwVbSr4QDjAGIifLDwpb2Pdl.jpg','https://image.tmdb.org/t/p/w1280/uOOtwVbSr4QDjAGIifLDwpb2Pdl.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000008','The Last of Us','the-last-of-us','SERIES','Joel y Ellie atraviesan Estados Unidos en un mundo postapocaliptico.',2023,60,8.8,'https://www.youtube.com/watch?v=uLtkt8BonwM','https://www.youtube.com/embed/uLtkt8BonwM','https://image.tmdb.org/t/p/w780/uKvVjHNqB5VmOrdxqAt2F7J78ED.jpg','https://image.tmdb.org/t/p/w1280/uKvVjHNqB5VmOrdxqAt2F7J78ED.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000009','Wednesday','wednesday','SERIES','Wednesday Addams investiga una ola de asesinatos en Nevermore Academy.',2022,50,8.1,'https://www.youtube.com/watch?v=Di310WS8zLk','https://www.youtube.com/embed/Di310WS8zLk','https://image.tmdb.org/t/p/w780/9PFonBhy4cQy7Jz20NpMygczOkv.jpg','https://image.tmdb.org/t/p/w1280/9PFonBhy4cQy7Jz20NpMygczOkv.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000010','The Boys','the-boys','SERIES','Un grupo de vigilantes intenta frenar a superheroes corruptos.',2019,60,8.7,'https://www.youtube.com/watch?v=M1bhOaLV4FU','https://www.youtube.com/embed/M1bhOaLV4FU','https://image.tmdb.org/t/p/w780/stTEycfG9928HYGEISBFaG1ngjM.jpg','https://image.tmdb.org/t/p/w1280/stTEycfG9928HYGEISBFaG1ngjM.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000011','House of the Dragon','house-of-the-dragon','SERIES','La dinastia Targaryen se rompe en una guerra civil por el trono.',2022,60,8.4,'https://www.youtube.com/watch?v=DotnJ7tTA34','https://www.youtube.com/embed/DotnJ7tTA34','https://image.tmdb.org/t/p/w780/z2yahl2uefxDCl0nogcRBstwruJ.jpg','https://image.tmdb.org/t/p/w1280/z2yahl2uefxDCl0nogcRBstwruJ.jpg',1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000012','Chernobyl','chernobyl','SERIES','Dramatizacion del desastre nuclear de 1986 y sus consecuencias humanas.',2019,65,9.3,'https://www.youtube.com/watch?v=s9APLXM9Ei8','https://www.youtube.com/embed/s9APLXM9Ei8','https://image.tmdb.org/t/p/w780/hlLXt2tOPT6RRnjiUmoxyG1LTFi.jpg','https://image.tmdb.org/t/p/w1280/hlLXt2tOPT6RRnjiUmoxyG1LTFi.jpg',1,NOW(),NOW());

INSERT INTO content_sections (content_id, section_id, sort_order, is_active, created_at, updated_at) VALUES
('50000000-0000-0000-0000-000000000001','10000000-0000-0000-0000-000000000001',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000001','10000000-0000-0000-0000-000000000004',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000002','10000000-0000-0000-0000-000000000004',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000002','10000000-0000-0000-0000-000000000001',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000003','10000000-0000-0000-0000-000000000006',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000003','10000000-0000-0000-0000-000000000001',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000004','10000000-0000-0000-0000-000000000002',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000004','10000000-0000-0000-0000-000000000004',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000005','10000000-0000-0000-0000-000000000005',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000005','10000000-0000-0000-0000-000000000002',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000006','10000000-0000-0000-0000-000000000004',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000006','10000000-0000-0000-0000-000000000005',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000007','10000000-0000-0000-0000-000000000003',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000007','10000000-0000-0000-0000-000000000001',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000008','10000000-0000-0000-0000-000000000003',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000008','10000000-0000-0000-0000-000000000005',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000009','10000000-0000-0000-0000-000000000003',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000009','10000000-0000-0000-0000-000000000002',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000010','10000000-0000-0000-0000-000000000003',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000010','10000000-0000-0000-0000-000000000006',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000011','10000000-0000-0000-0000-000000000003',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000011','10000000-0000-0000-0000-000000000002',1,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000012','10000000-0000-0000-0000-000000000003',0,1,NOW(),NOW()),
('50000000-0000-0000-0000-000000000012','10000000-0000-0000-0000-000000000005',1,1,NOW(),NOW());
