<?php
/*

======================================================================
統合APIファイル (api.php) - リファクタリング版

【変更点】
- 共通ヘルパー関数の利用
- エラーハンドリングの統一
- バリデーション処理の共通化
- コードの可読性向上
======================================================================
*/

define('IS_API_REQUEST', true);

//--------------------------------------------------------------
// 共通設定の読み込みと初期設定
//--------------------------------------------------------------
require_once __DIR__ . '/auth.php';
require_login();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_helpers.php';

// 返却データをJSONに指定
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

//====================================================
// GETリクエストの処理
//====================================================
if ($method === 'GET') {
    try {
        // セッションチェック
        if (isset($_GET['action']) && $_GET['action'] === 'check_session') {
            sendJsonSuccess(['status' => 'ok', 'message' => 'Session is active.']);
        }

        // 自分のプロフィール取得
        if (isset($_GET['action']) && $_GET['action'] === 'get_my_profile') {
            handleGetMyProfile($pdo);
        }

        // プロフィール一覧取得
        if (isset($_GET['action']) && $_GET['action'] === 'get_profiles') {
            handleGetProfiles($pdo);
        }

        // 投稿詳細取得（編集用）
        if (isset($_GET['id'])) {
            handleGetPostDetail($pdo, (int)$_GET['id']);
        }

        // 返信一覧取得
        if (isset($_GET['parent_id'])) {
            handleGetReplies($pdo, (int)$_GET['parent_id']);
        }

        // スレッド一覧取得（デフォルト）
        handleGetThreads($pdo);

    } catch (Exception $e) {
        handleDatabaseError($e);
    }
}

//====================================================
// POSTリクエストの処理
//====================================================
if ($method === 'POST') {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    try {
        $user_id = $_SESSION['user']['id'];

        // プロフィール更新
        if (isset($data['action']) && $data['action'] === 'update_profile') {
            handleUpdateProfile($pdo, $user_id, $data);
        }

        // 投稿削除
        if (isset($data['action']) && $data['action'] === 'delete') {
            handleDeletePost($pdo, $user_id, $data);
        }

        // 投稿編集
        if (isset($data['id']) && !empty($data['id'])) {
            handleUpdatePost($pdo, $user_id, $data);
        }

        // 返信投稿
        if (isset($data['parentpost_id']) && !empty($data['parentpost_id'])) {
            handleCreateReply($pdo, $user_id, $data);
        }

        // 返信編集
        if (isset($data['action']) && $data['action'] === 'edit_reply') {
            handleUpdateReply($pdo, $user_id, $data);
        }

        // 新規スレッド作成
        handleCreateThread($pdo, $user_id, $data);

    } catch (Exception $e) {
        handleDatabaseError($e);
    }
}

//====================================================
// ハンドラー関数群
//====================================================

/**
 * 自分のプロフィール取得
 */
function handleGetMyProfile($pdo) {
    $user_id = $_SESSION['user']['id'];
    
    $sql = "
        SELECT 
            u.username, 
            p.department, 
            p.hobbies, 
            p.comment 
        FROM 
            users AS u
        LEFT JOIN 
            profiles AS p ON u.id = p.user_id
        WHERE 
            u.id = :user_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        sendJsonError('ユーザーが見つかりません。', 404);
    }

    sendJsonSuccess($profile);
}

/**
 * プロフィール一覧取得
 */
function handleGetProfiles($pdo) {
    $limit = 10;
    $page = validatePageNumber($_GET['page'] ?? 1);
    $sortType = $_GET['sort'] ?? 'username';
    $order = $_GET['order'] ?? 'asc';

    // 全件取得
    $sql = "
        SELECT 
            u.id AS user_id, 
            u.username, 
            p.department, 
            p.hobbies, 
            p.comment,
            p.updated_at 
        FROM 
            users AS u
        LEFT JOIN 
            profiles AS p ON u.id = p.user_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ソート処理
    if ($sortType === 'newest') {
        usort($profiles, function($a, $b) {
            $a_date = $a['updated_at'] ?? '1970-01-01 00:00:00';
            $b_date = $b['updated_at'] ?? '1970-01-01 00:00:00';
            return strtotime($b_date) <=> strtotime($a_date);
        });
    } else {
        usort($profiles, function($a, $b) use ($order) {
            $cmp = strnatcmp($a['username'], $b['username']);
            return ($order === 'desc') ? -$cmp : $cmp;
        });
    }

    // ページング
    $total_profiles = count($profiles);
    $totalPages = calculateTotalPages($total_profiles, $limit);
    $offset = calculateOffset($page, $limit);
    $paged_profiles = array_slice($profiles, $offset, $limit);

    sendJsonSuccess([
        'profiles' => $paged_profiles,
        'current_user_id' => $_SESSION['user']['id'],
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);
}

/**
 * 投稿詳細取得（編集用）
 */
function handleGetPostDetail($pdo, $post_id) {
    $sql = "
        SELECT 
            p.id, 
            p.title, 
            p.body, 
            p.user_id 
        FROM 
            posts p 
        WHERE 
            p.id = :id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $post_id, PDO::PARAM_INT);
    $stmt->execute();
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post || !isPostOwner($pdo, $post_id, $_SESSION['user']['id'])) {
        sendJsonError('この投稿を編集する権限がありません。', 403);
    }

    sendJsonSuccess($post);
}

/**
 * 返信一覧取得
 */
function handleGetReplies($pdo, $parent_id) {
    $sql = "
        SELECT 
            p.id, 
            p.user_id, 
            p.body, 
            p.created_at, 
            p.updated_at,
            u.username
        FROM 
            posts AS p
        JOIN 
            users AS u ON p.user_id = u.id
        WHERE 
            p.parentpost_id = :parent_id
        ORDER BY 
            p.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':parent_id', $parent_id, PDO::PARAM_INT);
    $stmt->execute();
    $replies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJsonSuccess([
        'count' => count($replies),
        'replies' => $replies
    ]);
}

/**
 * スレッド一覧取得
 */
function handleGetThreads($pdo) {
    $limit = 10;
    $sort_column = validateSortColumn(
        $_GET['sort'] ?? 'created_at',
        ['created_at', 'updated_at']
    );
    $order = validateSortOrder($_GET['order'] ?? 'DESC');
    $page = validatePageNumber($_GET['page'] ?? 1);
    $offset = calculateOffset($page, $limit);

    // 総件数取得
    $count_sql = "SELECT COUNT(*) FROM posts WHERE parentpost_id IS NULL";
    $count_stmt = $pdo->query($count_sql);
    $total_threads = (int)$count_stmt->fetchColumn();
    $totalPages = calculateTotalPages($total_threads, $limit);

    // スレッド一覧取得
    $sql = "
        SELECT 
            p.id, 
            p.user_id, 
            p.title,
            p.body, 
            p.created_at, 
            p.updated_at,
            u.username,
            COUNT(r.id) AS reply_count
        FROM 
            posts AS p
        JOIN 
            users AS u ON p.user_id = u.id
        LEFT JOIN 
            posts AS r ON p.id = r.parentpost_id
        WHERE 
            p.parentpost_id IS NULL
        GROUP BY 
            p.id
        ORDER BY 
            p.{$sort_column} {$order}
        LIMIT :limit OFFSET :offset
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendJsonSuccess([
        'threads' => $threads,
        'current_user_id' => $_SESSION['user']['id'],
        'totalPages' => $totalPages,
        'currentPage' => $page
    ]);
}

/**
 * プロフィール更新
 */
function handleUpdateProfile($pdo, $user_id, $data) {
    $department = $data['department'] ?? '';
    $hobbies = $data['hobbies'] ?? [];
    $comment = $data['comment'] ?? '';
    $hobbies_string = implode(',', $hobbies);

    // UPSERT処理
    $check_sql = "SELECT id FROM profiles WHERE user_id = :user_id";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $check_stmt->execute();
    $existing_profile = $check_stmt->fetch();

    if ($existing_profile) {
        $sql = "UPDATE profiles SET department = :department, hobbies = :hobbies, comment = :comment, updated_at = NOW() WHERE user_id = :user_id";
    } else {
        $sql = "INSERT INTO profiles (user_id, department, hobbies, comment) VALUES (:user_id, :department, :hobbies, :comment)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':department', $department, PDO::PARAM_STR);
    $stmt->bindValue(':hobbies', $hobbies_string, PDO::PARAM_STR);
    $stmt->bindValue(':comment', $comment, PDO::PARAM_STR);
    $stmt->execute();
    
    sendJsonSuccess(['message' => 'プロフィールが更新されました。']);
}

/**
 * 投稿削除
 */
function handleDeletePost($pdo, $user_id, $data) {
    if (empty($data['id'])) {
        sendJsonError('削除する投稿のIDが指定されていません。', 400);
    }
    
    $post_id = (int)$data['id'];

    if (!isPostOwner($pdo, $post_id, $user_id)) {
        sendJsonError('この投稿を削除する権限がありません。', 403);
    }

    $sql = "DELETE FROM posts WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $post_id, PDO::PARAM_INT);
    $stmt->execute();
    
    sendJsonSuccess(['message' => '投稿が削除されました。']);
}

/**
 * 投稿編集
 */
function handleUpdatePost($pdo, $user_id, $data) {
    if (empty($data['body'])) {
        sendJsonError('投稿内容を入力してください。', 400);
    }

    $post_id = (int)$data['id'];
    $new_body = $data['body'];

    if (!isPostOwner($pdo, $post_id, $user_id)) {
        sendJsonError('この投稿を編集する権限がありません。', 403);
    }

    $sql = "UPDATE posts SET body = :body, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':body', $new_body, PDO::PARAM_STR);
    $stmt->bindValue(':id', $post_id, PDO::PARAM_INT);
    $stmt->execute();
    
    sendJsonSuccess(['message' => '投稿が更新されました。']);
}

/**
 * 返信投稿
 */
function handleCreateReply($pdo, $user_id, $data) {
    if (empty($data['body'])) {
        sendJsonError('返信内容を入力してください。', 400);
    }

    $sql = "INSERT INTO posts (user_id, body, parentpost_id) VALUES (:user_id, :body, :parentpost_id)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':body', $data['body'], PDO::PARAM_STR);
    $stmt->bindValue(':parentpost_id', (int)$data['parentpost_id'], PDO::PARAM_INT);
    $stmt->execute();

    sendJsonSuccess(['message' => '返信が投稿されました。'], 201);
}

/**
 * 返信編集
 */
function handleUpdateReply($pdo, $user_id, $data) {
    $reply_id = (int)($data['reply_id'] ?? 0);
    $body = trim($data['body'] ?? '');

    if ($reply_id <= 0 || $body === '') {
        sendJsonError('不正なデータです。', 400);
    }

    if (!isPostOwner($pdo, $reply_id, $user_id)) {
        sendJsonError('権限がありません。', 403);
    }

    $stmt = $pdo->prepare('UPDATE posts SET body = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$body, $reply_id]);

    sendJsonSuccess([
        'success' => true,
        'new_body' => htmlspecialchars($body, ENT_QUOTES, 'UTF-8')
    ]);
}

/**
 * 新規スレッド作成
 */
function handleCreateThread($pdo, $user_id, $data) {
    if (empty($data['title']) || empty($data['body'])) {
        sendJsonError('タイトルと本文は必須です。', 400);
    }

    $sql = "INSERT INTO posts (user_id, title, body) VALUES (:user_id, :title, :body)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindValue(':title', $data['title'], PDO::PARAM_STR);
    $stmt->bindValue(':body', $data['body'], PDO::PARAM_STR);
    $stmt->execute();

    sendJsonSuccess(['message' => '新しいスレッドが作成されました。'], 201);
}