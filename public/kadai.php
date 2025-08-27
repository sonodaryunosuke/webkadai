<?php
// =====================================
// bbs (kadai.php)
// =====================================

// DB接続（例: docker-compose のサービス名が mysql の場合）
$dbh = new PDO('mysql:host=mysql;dbname=example_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// 投稿処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body'])) {

    // 本文
    $body = trim($_POST['body']);
    if ($body === '') {
        header("HTTP/1.1 302 Found");
        header("Location: ./kadai.php");
        exit;
    }

    $image_filename = null;

    // 画像が付いている場合
    if (isset($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        // 画像MIME検証
        $mime = @mime_content_type($_FILES['image']['tmp_name']);
        if (!is_string($mime) || preg_match('#^image/#', $mime) !== 1) {
            // 画像以外は破棄してリダイレクト
            header("HTTP/1.1 302 Found");
            header("Location: ./kadai.php");
            exit;
        }

        // 元ファイルの拡張子
        $pathinfo  = pathinfo($_FILES['image']['name']);
        $extension = isset($pathinfo['extension']) ? strtolower($pathinfo['extension']) : 'jpg';
        // 新ファイル名（重複避け）
        $image_filename = time() . bin2hex(random_bytes(16)) . '.' . $extension;

        // 保存先（存在しない場合は作成しておくと安心）
        $uploadDir = '/var/www/upload/image';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $filepath = $uploadDir . '/' . $image_filename;

        // 保存
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $filepath)) {
            // 保存失敗時は画像なしで続行またはエラーにしてもOK
            $image_filename = null;
        }
    }

    // INSERT（←バインド名のタイプミスを修正）
    $sql = "INSERT INTO bbs_entries (body, image_filename) VALUES (:body, :image_filename)";
    $insert_sth = $dbh->prepare($sql);
    $insert_sth->execute([
        ':body' => $body,
        ':image_filename' => $image_filename,
    ]);

    // 二重投稿防止
    header("HTTP/1.1 302 Found");
    header("Location: ./kadai.php");
    exit;
}

// 一覧取得
$select_sth = $dbh->prepare('SELECT * FROM bbs_entries ORDER BY created_at DESC');
$select_sth->execute();
$entries = $select_sth->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>掲示板</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root {
    --bg: #f7f7f9;
    --card: #ffffff;
    --text: #222;
    --muted: #777;
    --border: #e5e7eb;
    --accent: #2563eb;
    --accent-weak: #dbeafe;
    --danger: #ef4444;
    --shadow: 0 8px 24px rgba(0,0,0,.06);
    --radius: 14px;
  }

  body {
    margin: 0; padding: 0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--text); background: var(--bg);
  }

  .container {
    max-width: 840px; margin: 40px auto; padding: 0 16px;
  }

  .card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 18px 20px;
  }

  h1 {
    font-size: 20px; margin: 0 0 16px;
    letter-spacing: .02em;
  }

  form.post-form {
    display: grid; gap: 12px;
  }

  textarea[name="body"] {
    width: 100%; min-height: 120px; resize: vertical;
    padding: 12px 14px; border-radius: 10px;
    border: 1px solid var(--border); outline: none;
    font-size: 14px; line-height: 1.6; background: #fff;
  }
  textarea[name="body"]:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-weak); }

  .file-row {
    display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
  }
  .hint { color: var(--muted); font-size: 12px; }

  .btn {
    appearance: none; border: none; cursor: pointer;
    background: var(--accent); color: #fff; font-weight: 600;
    padding: 10px 16px; border-radius: 10px; font-size: 14px;
  }

  .list { margin-top: 24px; display: grid; gap: 12px; }

  .entry {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px; box-shadow: var(--shadow);
  }

  .meta {
    display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; color: var(--muted);
    margin-bottom: 10px;
  }
  .meta strong { color: var(--text); }

  .entry-body {
    font-size: 14px; line-height: 1.8; white-space: pre-wrap;
  }

  /* サムネイル：幅を揃えつつ縦横比維持 */
  .thumb {
    margin-top: 10px;
    width: min(100%, 520px);
    aspect-ratio: 16/9; /* 画像高さがバラつくのを軽減（固定したい場合はここを調整） */
    overflow: hidden; border-radius: 12px; border: 1px solid var(--border);
    background: #fafafa; display: flex; align-items: center; justify-content: center;
  }
  .thumb img {
    width: 100%; height: 100%; object-fit: cover; object-position: center;
  }

  .post-actions {
    margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;
  }
  .reply-btn {
    appearance: none; border: 1px solid var(--border); background: #fff; color: var(--text);
    padding: 6px 10px; border-radius: 8px; font-size: 12px; cursor: pointer;
  }
  .reply-btn:hover { border-color: var(--accent); color: var(--accent); }

  /* 強調アニメ */
  @keyframes flashBg {
    0% { box-shadow: 0 0 0 0 rgba(37,99,235,.35); }
    100% { box-shadow: 0 0 0 12px rgba(37,99,235,0); }
  }
  .flash {
    animation: flashBg 1.2s ease-out 2;
    outline: 2px solid var(--accent);
    border-radius: var(--radius);
  }

  /* 引用アンカー */
  .entry-body a.reply-anchor {
    color: var(--accent); text-decoration: none; border-bottom: 1px dotted var(--accent);
  }
  .entry-body a.reply-anchor:hover { opacity: .8; }
</style>
</head>
<body>
  <div class="container">
    <div class="card">
      <h1>投稿フォーム</h1>
      <!-- フォームのPOST先はこのファイル自身 -->
      <form class="post-form" method="POST" action="./kadai.php" enctype="multipart/form-data">
        <textarea name="body" placeholder="本文（>>番号 でアンカー）" required></textarea>
        <div class="file-row">
          <input type="file" accept="image/*" name="image" id="imageInput">
          <span class="hint">画像は自動で 5MB 以下に圧縮して送信します（長辺は最大 2560px）。</span>
        </div>
        <button class="btn" type="submit">送信</button>
      </form>
    </div>

    <div class="list">
      <?php foreach ($entries as $entry): ?>
        <?php
          $id = (int)$entry['id'];
          $created = htmlspecialchars($entry['created_at'] ?? '', ENT_QUOTES, 'UTF-8');
          $safeBody = nl2br(htmlspecialchars($entry['body'] ?? '', ENT_QUOTES, 'UTF-8'));
          $img = $entry['image_filename'] ?? null;
        ?>
        <article class="entry" id="post-<?= $id ?>">
          <div class="meta">
            <div><strong>ID:</strong> <?= $id ?></div>
            <div><strong>日時:</strong> <?= $created ?></div>
          </div>
          <div class="entry-body"><?= $safeBody ?></div>

          <div class="post-actions">
            <button type="button" class="reply-btn" data-id="<?= $id ?>">返信（&gt;&gt;<?= $id ?>）</button>
          </div>

          <?php if (!empty($img)): ?>
            <div class="thumb">
              <!-- 表示URL：nginx/Apacheで /image を /var/www/upload/image にマッピングする想定
                   もしマッピングしていない場合は src を /upload/image/... に変更してください。-->
              <img src="/image/<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>

<script>
/**
 * =============================
 * ① 画像 5MB 以下リサイズ（維持）
 *  - 長辺 MAX_DIM に縮小しつつ JPEG 品質を段階調整
 * =============================
 */
const MAX_BYTES = 5 * 1024 * 1024; // 5MB
const MAX_DIM   = 2560;            // 長辺の上限

document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById("imageInput");
  if (imageInput) {
    imageInput.addEventListener("change", async () => {
      if (imageInput.files.length < 1) return;
      const file = imageInput.files[0];
      try {
        const resizedFile = await downsizeImageToUnder(file, MAX_BYTES, MAX_DIM);
        if (resizedFile.size > MAX_BYTES) {
          alert("5MB以下にできませんでした。別の画像を選んでください。");
          imageInput.value = "";
          return;
        }
        const dt = new DataTransfer();
        dt.items.add(resizedFile);
        imageInput.files = dt.files;
      } catch (e) {
        console.error(e);
        alert("画像の処理に失敗しました。別の画像を試してください。");
        imageInput.value = "";
      }
    });
  }

  // >>番号 をアンカーに変換 & クリック時に該当投稿を強調
  convertReplyAnchors();
  setupReplyButtons();
});

async function downsizeImageToUnder(file, maxBytes, maxDim) {
  const img = await readFileToImage(file);
  const canvas = document.createElement("canvas");
  const ctx = canvas.getContext("2d", { willReadFrequently: false });

  // 長辺を maxDim に収める
  let { width, height } = scaleToFit(img.naturalWidth, img.naturalHeight, maxDim);
  canvas.width = width;
  canvas.height = height;
  ctx.drawImage(img, 0, 0, width, height);

  // 品質を段階的に落としていく
  let quality = 0.92;
  const minQuality = 0.5;
  let blob = await canvasToBlob(canvas, "image/jpeg", quality);

  while (blob.size > maxBytes && quality > minQuality) {
    quality = Math.max(minQuality, quality - 0.07);
    blob = await canvasToBlob(canvas, "image/jpeg", quality);
  }

  // まだ大きければ解像度も段階的に落とす
  let step = 0;
  while (blob.size > maxBytes && step < 4) {
    step++;
    width = Math.floor(width * 0.8);
    height = Math.floor(height * 0.8);
    canvas.width = width; canvas.height = height;
    ctx.clearRect(0, 0, width, height);
    ctx.drawImage(img, 0, 0, width, height);
    blob = await canvasToBlob(canvas, "image/jpeg", quality);
  }

  const name = file.name.replace(/\.[^.]+$/, "") + ".jpg";
  return new File([blob], name, { type: "image/jpeg", lastModified: Date.now() });
}

function scaleToFit(w, h, maxDim) {
  const longSide = Math.max(w, h);
  if (longSide <= maxDim) return { width: w, height: h };
  const ratio = maxDim / longSide;
  return { width: Math.round(w * ratio), height: Math.round(h * ratio) };
}

function readFileToImage(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error("File read error"));
    reader.onload = () => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error("Image decode error"));
      img.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
}

function canvasToBlob(canvas, type, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) return reject(new Error("toBlob failed"));
      resolve(blob);
    }, type, quality);
  });
}

/**
 * =============================
 * ② レスアンカー & 返信補助
 * =============================
 */
function convertReplyAnchors() {
  document.querySelectorAll(".entry-body").forEach(node => {
    // 既に htmlspecialchars 済みを前提に &gt;&gt;123 をリンク化
    node.innerHTML = node.innerHTML.replace(/&gt;&gt;(\d+)/g, (m, num) => {
      return `<a href="#post-${num}" class="reply-anchor" data-target-id="${num}">&gt;&gt;${num}</a>`;
    });
  });

  document.addEventListener("click", (e) => {
    const a = e.target.closest("a.reply-anchor");
    if (!a) return;
    const id = a.getAttribute("data-target-id");
    const target = document.getElementById(`post-${id}`);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: "smooth", block: "center" });
      target.classList.add("flash");
      setTimeout(() => target.classList.remove("flash"), 1500);
    }
  });
}

function setupReplyButtons() {
  const textarea = document.querySelector('textarea[name="body"]');
  if (!textarea) return;
  document.querySelectorAll(".reply-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const id = btn.getAttribute("data-id");
      const insert = `>>${id}\n`;
      textarea.value = (textarea.value ? textarea.value + "\n" : "") + insert;
      textarea.focus();
    });
  });
}
</script>
</body>
</html>


