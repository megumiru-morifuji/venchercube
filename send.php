<?php
header('Content-Type: application/json; charset=UTF-8');

// 日本語エンコーディング
mb_language('Japanese');
mb_internal_encoding('UTF-8');

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'error' => '不正なリクエストです。'));
    exit;
}

// 受け取り（trim）
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$name     = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone    = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email    = isset($_POST['email']) ? trim($_POST['email']) : '';
$email_confirm = isset($_POST['email_confirm']) ? trim($_POST['email_confirm']) : '';
$message  = isset($_POST['message']) ? trim($_POST['message']) : '';

// サニタイズ（ヘッダーインジェクション対策など）
$name = preg_replace('/[\r\n\t]+/', ' ', strip_tags($name));
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$email_confirm = filter_var($email_confirm, FILTER_SANITIZE_EMAIL);
$phone = preg_replace('/[^\d]/', '', $phone); // 数字以外を除去
$message = str_replace(array("\r\n", "\r"), "\n", $message); // 改行統一

// サーバー側バリデーション
$errors = array();

if ($category === '') {
    $errors[] = 'カテゴリーを選択してください。';
}
if ($name === '') {
    $errors[] = '名前を入力してください。';
}
if ($phone === '') {
    $errors[] = '電話番号を入力してください。';
} elseif (!preg_match('/^[0-9]{10,11}$/', $phone)) {
    $errors[] = '電話番号の形式が正しくありません。';
}
if ($email === '') {
    $errors[] = 'メールアドレスを入力してください。';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'メールアドレスの形式が正しくありません。';
}
if ($email_confirm === '') {
    $errors[] = 'メールアドレス（確認）を入力してください。';
} elseif ($email !== $email_confirm) {
    $errors[] = 'メールアドレスが一致しません。';
}
if ($message === '') {
    $errors[] = 'お問い合わせ内容を入力してください。';
}

if (!empty($errors)) {
    echo json_encode(array('success' => false, 'error' => implode('<br>', $errors)));
    exit;
}

// カテゴリー名変換
$categoryName = $category;
switch ($category) {
    case 'sales':
        $categoryName = '営業代行事業';
        break;
    case 'real-estate':
        $categoryName = '不動産管理事業';
        break;
    case 'maintenance':
        $categoryName = 'メンテナンス事業';
        break;
    case 'other':
        $categoryName = 'その他・お問い合わせ';
        break;
}

// メール送信準備
$to = 'info@vt-tube.com';
$fromEmail = 'administrator@1934d84448f43b20.lolipop.jp';
$fromName = '株式会社ベンチャーチューブ';

// 件名（mb_encode_mimeheaderを削除）
$subject = "【お問い合わせ】{$categoryName} - {$name} 様より";

// 本文
$body = "お問い合わせがありました。\n\n";
$body .= "【カテゴリー】\n{$categoryName}\n\n";
$body .= "【お名前】\n{$name}\n\n";
$body .= "【電話番号】\n{$phone}\n\n";
$body .= "【メールアドレス】\n{$email}\n\n";
$body .= "【お問い合わせ内容】\n{$message}\n\n";
$body .= "----------------------------------------\n";
$body .= "送信日時: " . date('Y年m月d日 H:i:s') . "\n";
$body .= "送信元IP: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '') . "\n";

// ヘッダー
$fromNameEncoded = mb_encode_mimeheader($fromName, 'UTF-8');
$headers = "From: {$fromNameEncoded} <{$fromEmail}>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "Content-Transfer-Encoding: 8bit\r\n";
$headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

// エンベロープ送信者
$additional_params = "-f{$fromEmail}";

$sent = mb_send_mail($to, $subject, $body, $headers, $additional_params);

if ($sent) {
    // 自動返信（お客様宛）
    $autoSubject = "【自動返信】お問い合わせを受け付けました - 株式会社ベンチャーチューブ";
    $autoBody = "{$name} 様\n\n";
    $autoBody .= "この度は、株式会社ベンチャーチューブにお問い合わせいただき、誠にありがとうございます。\n\n";
    $autoBody .= "以下の内容でお問い合わせを受け付けました。\n\n";
    $autoBody .= "----------------------------------------\n";
    $autoBody .= "【カテゴリー】\n{$categoryName}\n\n";
    $autoBody .= "【お名前】\n{$name}\n\n";
    $autoBody .= "【電話番号】\n{$phone}\n\n";
    $autoBody .= "【メールアドレス】\n{$email}\n\n";
    $autoBody .= "【お問い合わせ内容】\n{$message}\n";
    $autoBody .= "----------------------------------------\n\n";
    $autoBody .= "※このメールは自動送信されています。\n\n";
    $autoBody .= "株式会社ベンチャーチューブ\n";
    $autoBody .= "〒467-0845 愛知県名古屋市瑞穂区河岸一丁目2-9\n";
    $autoBody .= "Email: info@vt-tube.com\n";

    $autoHeaders = "From: {$fromNameEncoded} <{$fromEmail}>\r\n";
    $autoHeaders .= "MIME-Version: 1.0\r\n";
    $autoHeaders .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $autoHeaders .= "Content-Transfer-Encoding: 8bit\r\n";
    mb_send_mail($email, $autoSubject, $autoBody, $autoHeaders, $additional_params);

    echo json_encode(array('success' => true));
} else {
    error_log('contact mail send failed: ' . print_r(array('to'=>$to,'subject'=>$subject), true));
    echo json_encode(array('success' => false, 'error' => 'メール送信に失敗しました。時間をおいて再度お試しください。'));
}
