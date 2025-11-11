/**
 * 共通JavaScript関数
 * 全ページで使用する関数を定義
 */

/**
 * fetchのラッパー関数。セッションタイムアウト(401)を共通処理する。
 * @param {string} url - リクエスト先のURL
 * @param {object} [options] - fetchに渡すオプション (method, bodyなど)
 * @returns {Promise<Response>} fetchのレスポンスオブジェクト
 */
async function apiFetch(url, options) {
    const response = await fetch(url, options);

    // 応答が401 Unauthorizedなら、セッション切れと判断
    if (response.status === 401) {
        alert('セッションタイム切れのため、ログイン画面にもどります');
        window.location.href = 'login.php';
        
        // エラーをthrowする代わりに、後続の処理を停止させる
        return new Promise(() => {});
    }

    return response;
}

/**
 * HTML特殊文字を安全に変換して画面に表示するための関数。
 * XSS（クロスサイトスクリプティング）防止。
 * @param {string} str - エスケープする文字列
 * @returns {string} エスケープされた文字列
 */
function escapeHTML(str) {
    if (!str) return '';
    
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    };
    
    return String(str).replace(/[&<>"']/g, c => map[c]);
}

/**
 * セッションチェック後に確認ダイアログを表示し、OKなら遷移する
 * @param {string} message - 確認メッセージ
 * @param {string} destinationUrl - 遷移先URL
 * @returns {Promise<void>}
 */
async function confirmWithSessionCheck(message, destinationUrl) {
    try {
        // セッションが有効かチェック
        await apiFetch('api.php?action=check_session');
        
        // セッションが有効なら確認ダイアログを表示
        if (confirm(message)) {
            window.location.href = destinationUrl;
        }
    } catch (error) {
        console.error("Session check failed:", error);
        if (error.message !== 'Session expired') {
            alert("エラーが発生しました: " + error.message);
        }
    }
}

/**
 * API通信のエラーハンドリング共通処理
 * @param {Response} response - fetchのレスポンス
 * @returns {Promise<object>} JSONパース後のデータ
 * @throws {Error} エラー時は例外をスロー
 */
async function handleApiResponse(response) {
    let result;
    try {
        result = await response.json();
    } catch (err) {
        console.error('JSON解析エラー:', err);
        throw new Error(`HTTPエラー: ${response.status}`);
    }
    
    if (!response.ok) {
        throw new Error(result.error || `HTTPエラー: ${response.status}`);
    }
    
    return result;
}