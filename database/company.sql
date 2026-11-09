INSERT IGNORE INTO content(kind,slug,title,description,published,sort_order) VALUES
('company','hero','Prime Pool Peninsula designs, builds and maintains pools for homes, resorts and communities.','Pool construction and engineering in Zamboanga City, Mindanao and beyond.',1,1),
('company','about','Prime Pool Peninsula','Founded in 2021, Prime Pool Peninsula Supply & Services grew from supplying industrial equipment into a pool construction and engineering company. Our roots in construction go back to 2018. From Zamboanga City, we design, build, install and maintain aquatic spaces across Mindanao and beyond.',1,2),
('company','vision','Our vision','Create aquatic spaces that bring people together through thoughtful design, innovation and environmental stewardship.',1,3),
('company','mission','Our mission','Deliver bespoke pool solutions through careful planning, expert design, sustainable practices and reliable service.',1,4),
('company','contact','Get in touch','MCLL Highway, Barangay Putik, Zamboanga City, Zamboanga del Sur 7000',1,9),
('company','email','Email','primepeninsula14@gmail.com',1,10),
('company','phone','Phone','0927-132-9760 / 0939-980-6274',1,11),
('service','design-build','Pool design and build','Custom pool design, engineering and construction for residential, resort and commercial properties.',1,1),
('service','resort-development','Resort development and construction','Concept development, planning, infrastructure and construction of resort amenities.',1,2),
('service','koi-jacuzzi','Koi ponds and jacuzzis','Custom ponds, jacuzzis, integrated water features, lighting and landscaping.',1,3),
('service','maintenance','Pool maintenance','Pool upkeep, equipment care and repair for new and existing facilities.',1,4),
('service','electrical','Electrical supply and installation','Electrical systems, equipment, wiring, lighting and maintenance.',1,5),
('service','fire-protection','Fire protection supply and installation','Fire protection equipment and installation services.',1,6),
('service','construction','General construction','General construction, painting and tiling services.',1,7),
('service','supplies','Equipment and supplies','Pool chemicals and equipment, solar street lights, steel, electrical supplies, fire protection accessories, elevator and escalator supplies, and paints.',1,8);
INSERT IGNORE INTO content(kind,slug,title,description,published,sort_order) VALUES
('company','leadership','Leadership and design','Cris Ian Magarso — President\nKimberly Jun Mabunga — Vice President\nAr. Reign John B. Capillo — Head of Design and Architect of Records\nAr. Jessie Boy L. Jandugan — Architect in Charge of Construction',1,5),
('company','team','Project and client services','Engr. Stormey Alama — Project Engineer\nRolando Lacson — Project Management Officer\nJaycee Rick S. Martinez — Corporate Secretary\nRoy Vincent Araneta — Project Sales Executive\nCorazon Magarso — Admin\nElpidio Samonte Jr. — Sales and Marketing Officer\nKimberly Senon and Edison De La Torre Jr. — Sales',1,6);
INSERT IGNORE INTO content_images(content_id,path,alt,sort_order)
SELECT id,'assets/supplies-13-1.webp','Pool equipment and accessories from the company catalogue',1 FROM content WHERE kind='service' AND slug='supplies';
INSERT IGNORE INTO content_images(content_id,path,alt,sort_order)
SELECT id,'assets/supplies-14-1.webp','Pool supplies from the company catalogue',2 FROM content WHERE kind='service' AND slug='supplies';
INSERT IGNORE INTO content_images(content_id,path,alt,sort_order)
SELECT id,'assets/supplies-15-1.webp','Pool supply selection from the company catalogue',3 FROM content WHERE kind='service' AND slug='supplies';
INSERT IGNORE INTO content_images(content_id,path,alt,sort_order)
SELECT id,'assets/supplies-15-2.webp','Additional pool supplies from the company catalogue',4 FROM content WHERE kind='service' AND slug='supplies';
INSERT IGNORE INTO message_templates(event,body) VALUES
('confirmation','Prime Pool Peninsula: Your appointment {form} is confirmed for {appointment}.'),
('appointment_reminder','Prime Pool Peninsula: Your appointment {form} is tomorrow at {appointment}.'),
('site_reminder','Prime Pool Peninsula: Your site visit for {form} is scheduled for {visit}.'),
('ongoing','Prime Pool Peninsula: Your project for {form} is now ongoing.'),
('completed','Prime Pool Peninsula: Your project for {form} is complete.');
