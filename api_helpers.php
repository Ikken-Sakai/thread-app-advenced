<?php
/**
 * API共通ヘルパー関数
 * api.php で使用する共通処理を定義
 */

/**
 * JSON形式で成功レスポンスを返す
 * @param mixed $data - 返却するデータ
 * @param int $status - HTTPステータスコード（デフォルト: 200）
 */
function sendJsonSuccess($data, $status = 200) {
    if ($status !== 200) {
        http_response_code($status);
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * JSON形式でエラーレスポンスを返す
 * @param string $message - エラーメッセージ
 * @param int $status - HTTPステータスコード（デフォルト: 400）
 */
function sendJsonError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * POSTリクエストのバリデーション
 * @param array $required_fields - 必須フィールドの配列
 * @param array $data - チェックするデータ
 * @return array|null - エラーがあれば配列、なければnull
 */
function validateRequiredFields($required_fields, $data) {
    $errors = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field]) && $data[$field] !== '0') {
            $errors[] = "{$field}は必須です。";
        }
    }
    
    return empty($errors) ? null : $errors;
}

/**
 * 投稿の所有者チェック
 * @param PDO $pdo - データベース接続
 * @param int $post_id - 投稿ID
 * @param int $user_id - ユーザーID
 * @return bool - 所有者ならtrue
 */
function isPostOwner($pdo, $post_id, $user_id) {
    $stmt = $pdo->prepare('SELECT user_id FROM posts WHERE id = ?');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    return $post && (int)$post['user_id'] === (int)$user_id;
}

/**
 * プロフィールの所有者チェック
 * @param int $profile_user_id - プロフィールのユーザーID
 * @param int $current_user_id - 現在のユーザーID
 * @return bool - 所有者ならtrue
 */
function isProfileOwner($profile_user_id, $current_user_id) {
    return (int)$profile_user_id === (int)$current_user_id;
}

/**
 * ページネーション用のOFFSET計算
 * @param int $page - ページ番号
 * @param int $limit - 1ページあたりの件数
 * @return int - OFFSET値
 */
function calculateOffset($page, $limit) {
    return ($page - 1) * $limit;
}

/**
 * 総ページ数の計算
 * @param int $total_items - 総件数
 * @param int $limit - 1ページあたりの件数
 * @return int - 総ページ数
 */
function calculateTotalPages($total_items, $limit) {
    return (int)ceil($total_items / $limit);
}

/**
 * ソートパラメータのバリデーション
 * @param string $sort - ソート項目
 * @param array $allowed_columns - 許可されたカラムのリスト
 * @return string - バリデーション済みのソート項目
 */
function validateSortColumn($sort, $allowed_columns) {
    return in_array($sort, $allowed_columns, true) ? $sort : $allowed_columns[0];
}

/**
 * ソート順のバリデーション
 * @param string $order - ソート順 (asc/desc)
 * @return string - バリデーション済みのソート順 (ASC/DESC)
 */
function validateSortOrder($order) {
    return strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
}

/**
 * ページ番号のバリデーション
 * @param mixed $page - ページ番号
 * @return int - バリデーション済みのページ番号（最小1）
 */
function validatePageNumber($page) {
    $validated = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $validated !== false ? $validated : 1;
}

/**
 * データベースエラーハンドリング
 * @param Exception $e - 例外オブジェクト
 */
function handleDatabaseError($e) {
    error_log('Database Error: ' . $e->getMessage());
    sendJsonError('データベースエラーが発生しました。', 500);
}