-- SQLite
-- Включаем внешние ключи (если нужно)
PRAGMA foreign_keys = ON;

-- 1) Посмотреть расхождения (рекомендуемая проверка)
SELECT
  p.id AS post_id,
  COALESCE(p.comments, 0) AS cached_comments,
  COALESCE(cnt.actual_comments, 0) AS actual_comments
FROM posts p
LEFT JOIN (
  SELECT post_id, COUNT(*) AS actual_comments
  FROM comments
  GROUP BY post_id
) AS cnt ON cnt.post_id = p.id
WHERE COALESCE(p.comments, 0) != COALESCE(cnt.actual_comments, 0)
ORDER BY ABS(COALESCE(p.comments,0) - COALESCE(cnt.actual_comments,0)) DESC;

-- 2) Бэкап текущих значений счётчиков (на всякий пожарный)
CREATE TABLE IF NOT EXISTS posts_comments_backup AS
SELECT id AS post_id, comments AS old_comments, CURRENT_TIMESTAMP AS backup_ts
FROM posts;

-- 3) Индекс для ускорения подсчёта (если ещё нет)
CREATE INDEX IF NOT EXISTS idx_comments_post_id ON comments(post_id);

-- 4) Исправление счётчиков — используем SAVEPOINT вместо BEGIN/COMMIT
SAVEPOINT fix_comments;
  UPDATE posts
  SET comments = COALESCE((
      SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id
  ), 0)
  WHERE COALESCE(comments, 0) != COALESCE((
      SELECT COUNT(*) FROM comments WHERE comments.post_id = posts.id
  ), 0);
RELEASE fix_comments;

-- 5) Проверка — должно быть 0 несоответствий
SELECT COUNT(*) AS mismatches_remaining
FROM posts p
LEFT JOIN (
  SELECT post_id, COUNT(*) AS actual_comments
  FROM comments
  GROUP BY post_id
) AS cnt ON cnt.post_id = p.id
WHERE COALESCE(p.comments, 0) != COALESCE(cnt.actual_comments, 0);

-- 6) Безопасно пересоздаём триггеры (на всякий случай — удалим старые)
DROP TRIGGER IF EXISTS trg_comments_after_insert;
DROP TRIGGER IF EXISTS trg_comments_after_delete;
DROP TRIGGER IF EXISTS trg_comments_after_update;

CREATE TRIGGER trg_comments_after_insert
AFTER INSERT ON comments
FOR EACH ROW
WHEN NEW.post_id IS NOT NULL
BEGIN
  UPDATE posts
  SET comments = COALESCE(comments, 0) + 1
  WHERE id = NEW.post_id;
END;

CREATE TRIGGER trg_comments_after_delete
AFTER DELETE ON comments
FOR EACH ROW
WHEN OLD.post_id IS NOT NULL
BEGIN
  UPDATE posts
  SET comments = CASE WHEN comments IS NULL OR comments <= 1 THEN 0 ELSE comments - 1 END
  WHERE id = OLD.post_id;
END;

CREATE TRIGGER trg_comments_after_update
AFTER UPDATE OF post_id ON comments
FOR EACH ROW
BEGIN
  -- уменьшить старый пост (если был)
  UPDATE posts
  SET comments = CASE WHEN comments IS NULL OR comments <= 1 THEN 0 ELSE comments - 1 END
  WHERE id = OLD.post_id AND OLD.post_id IS NOT NULL;

  -- увеличить новый пост (если стал)
  UPDATE posts
  SET comments = COALESCE(comments, 0) + 1
  WHERE id = NEW.post_id AND NEW.post_id IS NOT NULL;
END;