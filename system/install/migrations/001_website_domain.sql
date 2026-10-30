-- 001: the website moved to nubiakcestates.com (company email unchanged).
-- Safe to run more than once. phpMyAdmin > your database > SQL tab > paste > Go.

UPDATE settings
SET website = 'nubiakcestates.com'
WHERE id = 1 AND website IN ('nubiakcestates.co.ke', 'www.nubiakcestates.co.ke', 'https://nubiakcestates.co.ke');
