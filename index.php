<?php

$apiKey = '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'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (empty($apiKey) || strpos($apiKey, 'sk-') !== 0) {
        http_response_code(500);
        echo json_encode([
            "error" => [
                "message" => "Server configuration error: API Key is missing or invalid. Please set a valid API key."
            ]
        ]);
        exit;
    }
    
    // Read and validate input
    $input = json_decode(file_get_contents("php://input"), true);
    $messages = $input['messages'] ?? null;
    $selectedModel = $input['model'] ?? null;
    
    // Validate messages array
    if (!is_array($messages)) {
        http_response_code(400);
        echo json_encode([
            "error" => [
                "message" => "Invalid messages array received."
            ]
        ]);
        exit;
    }
    
    // Validate model selection
    if (empty($selectedModel) || !is_string($selectedModel)) {
        http_response_code(400);
        echo json_encode([
            "error" => [
                "message" => "No AI model specified in the request."
            ]
        ]);
        exit;
    }
    
    // Format messages for the API
    $apiMessages = array_map(function($msg) {
        return [
            'role' => isset($msg['role']) && is_string($msg['role']) ? $msg['role'] : 'user',
            'content' => isset($msg['content']) && is_string($msg['content']) ? $msg['content'] : ''
        ];
    }, $messages);
    
    // Ensure there's at least one valid message
    if (empty($apiMessages)) {
        http_response_code(400);
        echo json_encode([
            "error" => [
                "message" => "No valid messages to send to the API."
            ]
        ]);
        exit;
    }
    
    // Prepare API request payload
    $payload = [
        "model" => $selectedModel,
        "messages" => $apiMessages,
        // Optional parameters (uncomment and adjust as needed)
        // "temperature" => 0.7,
        // "max_tokens" => 1024,
        // "top_p" => 0.9,
        // "frequency_penalty" => 0,
        // "presence_penalty" => 0,
    ];
    
    // Set up and execute API request
    $apiUrl = "https://openrouter.ai/api/v1/chat/completions";
    $ch = curl_init($apiUrl);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ],
        CURLOPT_TIMEOUT => 180 // 3 minute timeout
    ]);
    
    $response = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    
    curl_close($ch);
    
    // Handle response and errors
    if ($response === false) {
        // Network or cURL error
        http_response_code(500);
        error_log("cURL Error ($curlErrno): " . $curlError);
        echo json_encode([
            "error" => [
                "message" => "Network error: " . $curlError
            ]
        ]);
    } elseif ($httpStatus >= 400) {
        // API returned an error
        http_response_code($httpStatus);
        $responseData = json_decode($response, true);
        $apiError = $responseData['error']['message'] ?? $responseData['message'] ?? $responseData['error'] ?? $response;
        
        error_log("API Error (Status: $httpStatus): " . (is_string($apiError) ? $apiError : json_encode($apiError)));
        echo json_encode([
            "error" => [
                "message" => "API Request Failed (Status: $httpStatus): " . (is_string($apiError) ? $apiError : json_encode($apiError))
            ]
        ]);
    } else {
        // Success - forward the API response to the client
        echo $response;
    }
    
    exit;
}

// If not a POST request, serve the HTML interface
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Leirad AI Chat - A simple interface to chat with AI models">
    <title>Leirad AI Chat</title>

    <!-- External CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/vs2015.min.css">

    <style>
    /* === Theme Variables === */
    :root {
        /* Colors */
        --primary-color: #0d6efd;
        --secondary-color: #6c757d;
        --dark-bg: #212529;
        --darker-bg: #1a1d20;
        --light-text: #f8f9fa;
        --border-color: #495057;
        --code-bg: #1e1e1e;

        /* Layout sizes */
        --sidebar-width: 260px;
        --header-height: 60px;
        --footer-height: auto;
        --message-max-width: 80%;

        /* Element colors */
        --assistant-bubble-bg: #343a40;
        --user-bubble-bg: var(--primary-color);
        --user-bubble-text: white;
        --input-bg: #2b2f33;
    }

    /* === Base Styles === */
    html,
    body {
        height: 100%;
        margin: 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        color: var(--light-text);
        background-color: var(--dark-bg);
        overflow: hidden;
    }

    .chat-container {
        display: flex;
        height: 100vh;
    }

    /* === Sidebar === */
    .sidebar {
        width: var(--sidebar-width);
        background-color: var(--darker-bg);
        border-right: 1px solid var(--border-color);
        display: flex;
        flex-direction: column;
        flex-shrink: 0;
        z-index: 1035;
        transition: transform 0.3s ease;
    }

    .sidebar-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: var(--header-height);
        flex-shrink: 0;
    }

    .sidebar-title {
        font-weight: 600;
        font-size: 1.15rem;
        margin: 0;
        color: var(--light-text);
    }

    .new-chat-btn {
        margin: 1rem;
        flex-shrink: 0;
    }

    .chat-list {
        flex-grow: 1;
        overflow-y: auto;
        padding: 0.5rem;
        scrollbar-width: thin;
        scrollbar-color: var(--border-color) transparent;
    }

    /* Custom scrollbar styles */
    .chat-list::-webkit-scrollbar {
        width: 6px;
    }

    .chat-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .chat-list::-webkit-scrollbar-thumb {
        background-color: var(--border-color);
        border-radius: 3px;
    }

    .chat-item {
        padding: 0.75rem 1rem;
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        transition: background-color 0.2s ease;
        color: var(--light-text);
        text-decoration: none;
        border: 1px solid transparent;
    }

    .chat-item:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .chat-item.active {
        background-color: rgba(13, 110, 253, 0.15);
        border-color: rgba(13, 110, 253, 0.3);
    }

    .chat-item-icon {
        opacity: 0.8;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .chat-item-title {
        flex-grow: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.9rem;
    }

    .delete-chat-btn {
        color: var(--secondary-color) !important;
        opacity: 0.8;
        transition: opacity 0.2s ease, color 0.2s ease;
        font-size: 0.9rem;
    }

    .delete-chat-btn:hover {
        color: #dc3545 !important;
        opacity: 1;
    }

    /* === Main Chat Area === */
    .chat-main {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
        background-color: var(--dark-bg);
    }

    .chat-header {
        height: var(--header-height);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1rem;
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
        background-color: var(--darker-bg);
        flex-wrap: wrap;
        gap: 1rem;
    }

    .chat-header-left {
        display: flex;
        align-items: center;
        flex-grow: 1;
    }

    .chat-header-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .chat-title {
        font-weight: 500;
        margin: 0;
        font-size: 1.1rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: var(--light-text);
    }

    .mobile-sidebar-toggle {
        display: none;
        color: var(--light-text);
    }

    .model-select-container {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .model-select {
        background-color: var(--input-bg);
        color: var(--light-text);
        border: 1px solid var(--border-color);
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
        cursor: pointer;
    }

    .model-select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        background-color: var(--input-bg);
        color: var(--light-text);
    }

    .model-select option {
        background-color: var(--input-bg);
        color: var(--light-text);
    }

    .chat-messages {
        flex-grow: 1;
        overflow-y: auto;
        padding: 1.5rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        scrollbar-width: thin;
        scrollbar-color: var(--border-color) transparent;
    }

    /* Custom scrollbar for message area */
    .chat-messages::-webkit-scrollbar {
        width: 6px;
    }

    .chat-messages::-webkit-scrollbar-track {
        background: transparent;
    }

    .chat-messages::-webkit-scrollbar-thumb {
        background-color: var(--border-color);
        border-radius: 3px;
    }

    /* === Message Styling === */
    .message-wrapper {
        display: flex;
        flex-direction: column;
        max-width: var(--message-max-width);
        position: relative;
        animation: fadeIn 0.3s ease-out;
    }

    .message-wrapper.user {
        align-self: flex-end;
        align-items: flex-end;
    }

    .message-wrapper.assistant {
        align-self: flex-start;
        align-items: flex-start;
    }

    .message {
        padding: 0.75rem 1rem;
        border-radius: 1rem;
        width: fit-content;
        max-width: 100%;
        position: relative;
        word-break: break-word;
    }

    .message.user {
        background-color: var(--user-bubble-bg);
        color: var(--user-bubble-text);
        border-bottom-right-radius: 0.25rem;
    }

    .message.assistant {
        background-color: var(--assistant-bubble-bg);
        color: var(--light-text);
        border-bottom-left-radius: 0.25rem;
    }

    .message-content {
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.95rem;
        line-height: 1.6;
    }

    .message-content p {
        margin-bottom: 0.5em;
    }

    .message-content p:last-child {
        margin-bottom: 0;
    }

    .message-content ul,
    .message-content ol {
        padding-left: 1.5em;
        margin-top: 0.5em;
        margin-bottom: 0.5em;
    }

    .message-content li {
        margin-bottom: 0.25em;
    }

    .message-meta {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: 0.3rem;
        height: 20px;
        opacity: 0.7;
    }

    .message-wrapper.assistant .message-meta {
        justify-content: flex-start;
    }

    .message-timestamp {
        font-size: 0.75rem;
        opacity: 0.8;
        margin-right: 0.5rem;
        line-height: 1;
        color: var(--secondary-color);
    }

    .message-wrapper.assistant .message-timestamp {
        margin-left: 0.5rem;
        margin-right: 0;
    }

    .message-actions {
        display: flex;
        gap: 0.3rem;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .message-wrapper.user:hover .message-actions,
    .message-wrapper.user:focus-within .message-actions {
        opacity: 1;
    }

    .action-btn {
        background: rgba(255, 255, 255, 0.1);
        border: none;
        color: var(--light-text);
        opacity: 0.8;
        cursor: pointer;
        padding: 2px 5px;
        font-size: 0.75rem;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 3px;
        line-height: 1;
        transition: opacity 0.2s ease, background-color 0.2s ease;
    }

    .action-btn i {
        font-size: 0.8rem;
    }

    .action-btn:hover {
        opacity: 1;
        background: rgba(255, 255, 255, 0.2);
    }

    .action-btn.copied {
        color: #28a745;
        background: rgba(40, 167, 69, 0.2);
    }

    /* === Code Block Styling === */
    pre {
        margin: 1em 0;
        border-radius: 0.375rem;
        overflow: hidden;
        background-color: var(--code-bg);
        border: 1px solid var(--border-color);
        font-size: 0.875rem;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-all;
    }

    .code-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.5rem 1rem;
        background-color: rgba(0, 0, 0, 0.2);
        border-bottom: 1px solid var(--border-color);
        font-family: "Consolas", "Monaco", monospace;
        font-size: 0.8rem;
        color: var(--secondary-color);
    }

    .code-language {
        text-transform: lowercase;
        font-weight: 500;
    }

    .copy-btn {
        background: none;
        border: none;
        color: var(--light-text);
        cursor: pointer;
        padding: 2px 8px;
        font-size: 0.8rem;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
        opacity: 0.7;
        transition: opacity 0.2s, background-color 0.2s;
    }

    .copy-btn i {
        font-size: 0.9rem;
    }

    .copy-btn:hover {
        background-color: rgba(255, 255, 255, 0.1);
        opacity: 1;
    }

    .copy-btn.copied {
        color: #28a745;
        background-color: rgba(40, 167, 69, 0.2);
    }

    /* Highlight.js custom styles */
    pre code.hljs {
        padding: 1rem;
        display: block;
        overflow-x: auto;
        background: transparent;
        color: #d4d4d4;
        line-height: 1.5;
        scrollbar-width: thin;
        scrollbar-color: var(--border-color) transparent;
    }

    pre code.hljs::-webkit-scrollbar {
        height: 6px;
        width: 6px;
    }

    pre code.hljs::-webkit-scrollbar-track {
        background: transparent;
    }

    pre code.hljs::-webkit-scrollbar-thumb {
        background-color: var(--border-color);
        border-radius: 3px;
    }

    /* === Input Area === */
    .chat-input-container {
        background-color: var(--dark-bg);
        border-top: 1px solid var(--border-color);
        padding: 1rem;
        flex-shrink: 0;
    }

    .chat-form {
        display: flex;
        align-items: flex-end;
        gap: 0.5rem;
        position: relative;
    }

    .chat-input {
        flex-grow: 1;
        padding: 0.75rem 3.5rem 0.75rem 1rem;
        border-radius: 1.5rem;
        border: 1px solid var(--border-color);
        background-color: var(--input-bg);
        color: var(--light-text);
        resize: none;
        font-size: 1rem;
        line-height: 1.5;
        max-height: 200px;
        overflow-y: auto;
        scrollbar-width: none;
    }

    .chat-input::-webkit-scrollbar {
        display: none;
    }

    .chat-input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
        background-color: var(--input-bg);
        color: var(--light-text);
    }

    .chat-input:disabled {
        background-color: var(--input-bg);
        opacity: 0.6;
        cursor: not-allowed;
    }

    .send-btn {
        position: absolute;
        right: 6px;
        bottom: 6px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        background-color: var(--primary-color);
        border: none;
        color: white;
        transition: background-color 0.2s;
    }

    .send-btn:hover:not(:disabled) {
        background-color: #0b5ed7;
    }

    .send-btn:disabled {
        background-color: var(--secondary-color);
        cursor: not-allowed;
        opacity: 0.6;
    }

    .send-btn i {
        font-size: 1.2rem;
    }

    /* === Typing Indicator and Animation === */
    .typing-indicator-wrapper {
        align-self: flex-start;
        display: flex;
        align-items: center;
    }

    .typing-indicator {
        display: flex;
        align-items: center;
        padding: 0.75rem 1rem;
        background-color: var(--assistant-bubble-bg);
        border-radius: 1rem;
        border-bottom-left-radius: 0.25rem;
        width: fit-content;
    }

    .typing-dots {
        display: flex;
        gap: 5px;
    }

    .typing-dot {
        width: 8px;
        height: 8px;
        background-color: var(--light-text);
        border-radius: 50%;
        opacity: 0.7;
        animation: typingAnimation 1.4s infinite ease-in-out both;
    }

    .typing-dot:nth-child(1) {
        animation-delay: -0.32s;
    }

    .typing-dot:nth-child(2) {
        animation-delay: -0.16s;
    }

    .typing-dot:nth-child(3) {
        animation-delay: 0s;
    }

    /* Typing animation for responses */
    .typing-text {
        border-right: 2px solid var(--primary-color);
        animation: cursor-blink 0.8s step-end infinite;
        white-space: pre-wrap;
        word-break: break-word;
    }

    @keyframes cursor-blink {

        from,
        to {
            border-color: transparent;
        }

        50% {
            border-color: var(--primary-color);
        }
    }

    @keyframes typingAnimation {

        0%,
        80%,
        100% {
            transform: scale(0);
            opacity: 0.5;
        }

        40% {
            transform: scale(1.0);
            opacity: 1;
        }
    }

    /* === Modal Styles === */
    .modal-content {
        background-color: var(--dark-bg);
        color: var(--light-text);
        border: 1px solid var(--border-color);
    }

    .modal-header,
    .modal-footer {
        border-color: var(--border-color);
    }

    .modal-header .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    }

    .modal textarea {
        background-color: var(--input-bg);
        color: var(--light-text);
        border-color: var(--border-color);
        min-height: 150px;
    }

    .modal textarea:focus {
        background-color: var(--input-bg);
        color: var(--light-text);
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    /* === Toast Notifications === */
    .toast-container {
        z-index: 1090;
        position: fixed;
        top: 1rem;
        left: 50%;
        transform: translateX(-50%);
        padding: 0;
        width: auto;
        max-width: 90%;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .toast {
        color: var(--light-text);
        background-color: var(--dark-bg);
        border: 1px solid var(--border-color);
        backdrop-filter: blur(5px);
        background-color: rgba(33, 37, 41, 0.85);
        margin-bottom: 0.75rem;
        width: 320px;
        max-width: 100%;
    }

    .toast-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        color: var(--light-text);
        background-color: transparent !important;
        border-color: rgba(255, 255, 255, 0.1);
    }

    .toast.bg-warning .toast-header {
        color: var(--dark-bg);
        border-color: rgba(0, 0, 0, 0.1);
    }

    .toast.bg-warning {
        color: var(--dark-bg);
        background-color: rgba(255, 193, 7, 0.9);
        border-color: rgba(255, 193, 7, 1);
    }

    .toast .btn-close {
        filter: invert(1) grayscale(100%) brightness(200%);
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    }

    .toast.bg-warning .btn-close {
        filter: none;
        background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
    }

    /* === Responsive Design === */
    @media (max-width: 768px) {
        :root {
            --message-max-width: 90%;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            transform: translateX(-100%);
            border-right: 1px solid var(--border-color);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .mobile-sidebar-toggle {
            display: block;
        }

        .chat-header {
            flex-direction: column;
            align-items: stretch;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            height: auto;
        }

        .chat-header-left,
        .chat-header-right {
            width: 100%;
            justify-content: space-between;
        }

        .chat-header-left {
            flex-grow: 0;
        }

        .chat-messages {
            padding: 1rem 0.75rem;
        }

        .chat-input-container {
            padding: 0.75rem;
        }

        .chat-title {
            font-size: 1rem;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .desktop-only {
            display: none !important;
        }

        .mobile-only {
            display: inline-block !important;
        }

        body.sidebar-open {
            overflow: hidden;
        }

        .toast-container {
            padding: 0.75rem !important;
            max-width: 85%;
        }

        .toast {
            width: 100%;
        }
    }

    /* Utility classes */
    .desktop-only {
        display: inline-block;
    }

    .mobile-only {
        display: none;
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div aria-live="polite" aria-atomic="true" class="position-fixed top-0 start-0 p-3">
        <div id="toastContainer" class="toast-container">
            <!-- Toasts will be dynamically added here -->
        </div>
    </div>

    <div class="chat-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2 class="sidebar-title">Chat History</h2>
                <button class="btn btn-icon mobile-only text-light" id="closeSidebar" aria-label="Close sidebar">
                    <i class="bi bi-x-lg fs-5"></i>
                </button>
            </div>
            <button class="btn btn-primary mx-3 my-2 new-chat-btn" id="newChatBtn">
                <i class="bi bi-plus-lg me-2"></i>New Chat
            </button>
            <div class="chat-list" id="chatList">
                <!-- Chat sessions will be loaded here -->
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="chat-main">
            <div class="chat-header">
                <div class="chat-header-left">
                    <!-- Mobile sidebar toggle -->
                    <button class="btn btn-icon mobile-sidebar-toggle me-2 text-light" id="openSidebar"
                        aria-label="Open sidebar">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    <h4 class="chat-title mb-0" id="currentChatTitle">New Chat</h4>
                </div>
                <div class="chat-header-right">
                    <div class="model-select-container">
                        <label for="modelSelect" class="form-label mb-0 text-secondary d-none d-md-block">Model:</label>
                        <select id="modelSelect" class="form-select form-select-sm model-select"
                            aria-label="Select AI Model">
                            <!-- Model options will be populated by JS -->
                        </select>
                    </div>
                    <!-- Desktop action buttons -->
                    <button class="btn btn-sm btn-outline-secondary desktop-only" id="clearChatBtn"
                        title="Clear current chat">
                        <i class="bi bi-trash me-1"></i>Clear
                    </button>
                    <button class="btn btn-sm btn-outline-secondary desktop-only" id="exportChatBtn"
                        title="Export chat as JSON">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <!-- Mobile action buttons -->
                    <button class="btn btn-sm btn-outline-secondary mobile-only" id="clearChatBtnMobile"
                        title="Clear current chat">
                        <i class="bi bi-trash"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary mobile-only" id="exportChatBtnMobile"
                        title="Export chat as JSON">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
            </div>

            <div class="chat-messages" id="messagesContainer">
                <!-- Welcome message -->
                <p class="text-center text-secondary mt-5">Start a conversation by typing a message below.</p>
            </div>

            <div class="chat-input-container">
                <form class="chat-form" id="messageForm">
                    <textarea class="chat-input form-control" id="userInput" placeholder="Type your message here..."
                        rows="1" aria-label="Chat input"></textarea>
                    <button type="submit" class="btn send-btn" id="sendBtn" aria-label="Send message" disabled>
                        <i class="bi bi-send"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Message Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea class="form-control" id="editMessageText" rows="6"
                        aria-label="Edit message text"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEditBtn">Save & Regenerate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- External Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>

    <script>
    /**
     * Leirad AI Chat Application - Client-side JavaScript
     * 
     * This script handles the chat interface, user interactions,
     * and communication with the server-side PHP script.
     */
    document.addEventListener('DOMContentLoaded', function() {

        // ======== DOM Elements ========
        const sidebar = document.getElementById('sidebar');
        const openSidebarBtn = document.getElementById('openSidebar');
        const closeSidebarBtn = document.getElementById('closeSidebar');
        const newChatBtn = document.getElementById('newChatBtn');
        const clearChatBtn = document.getElementById('clearChatBtn');
        const exportChatBtn = document.getElementById('exportChatBtn');
        const clearChatBtnMobile = document.getElementById('clearChatBtnMobile');
        const exportChatBtnMobile = document.getElementById('exportChatBtnMobile');
        const chatList = document.getElementById('chatList');
        const messagesContainer = document.getElementById('messagesContainer');
        const messageForm = document.getElementById('messageForm');
        const userInput = document.getElementById('userInput');
        const sendBtn = document.getElementById('sendBtn');
        const currentChatTitle = document.getElementById('currentChatTitle');
        const modelSelect = document.getElementById('modelSelect');
        const editModalEl = document.getElementById('editModal');
        const editModal = new bootstrap.Modal(editModalEl);
        const editMessageText = document.getElementById('editMessageText');
        const saveEditBtn = document.getElementById('saveEditBtn');
        const toastContainer = document.getElementById('toastContainer');

        // Create overlay for mobile sidebar
        const sidebarOverlay = document.createElement('div');
        sidebarOverlay.style.cssText =
            'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1030; display: none; opacity: 0; transition: opacity 0.3s ease;';
        document.body.appendChild(sidebarOverlay);

        // ======== State Variables ========
        let chats = {}; // Stores all chat sessions
        let currentChatId = null; // Currently active chat ID
        let editingMessageId = null; // ID of message being edited
        let isSending = false; // Flag to prevent multiple API requests

        // ======== Available AI Models ========
        const availableModels = [{
                id: 'deepseek/deepseek-r1:free',
                name: 'DeepSeek R1 (Free)'
            },
            {
                id: 'qwen/qwen3-235b-a22b:free',
                name: 'Qwen 235B (Free)'
            },
            {
                id: 'google/gemini-2.5-flash-preview',
                name: 'Gemini 2.5 Flash (Preview)'
            },
            {
                id: 'deepseek/deepseek-prover-v2:free',
                name: 'DeepSeek Prover V2 (Free)'
            },
            {
                id: 'meta-llama/llama-4-maverick:free',
                name: 'Llama 4 Maverick (Free)'
            },
            {
                id: 'openai/gpt-4o-mini',
                name: 'GPT-4o Mini'
            },
            {
                id: 'anthropic/claude-3-5-sonnet',
                name: 'Claude 3.5 Sonnet'
            },
            {
                id: 'microsoft/phi-4-reasoning-plus:free',
                name: 'MS Phi-4 Reasoning+ (Free)'
            },

        ];

        const defaultModel = 'deepseek/deepseek-r1:free';

        // ======== Utility Functions ========

        // Track active toasts to prevent duplicates
        const activeToasts = new Map();

        /**
         * Escapes HTML special characters to prevent XSS
         * @param {string} str - The string to escape
         * @return {string} Escaped HTML string
         */
        function escapeHTML(str) {
            if (typeof str !== 'string' || str === null) {
                str = String(str);
            }
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /**
         * Shows a toast notification
         * @param {string} message - Message to display
         * @param {string} type - 'success', 'error', 'warning', or 'info'
         * @param {number} duration - How long to show the toast in ms
         */
        function showToast(message, type = 'info', duration = 5000) {
            if (!toastContainer) {
                console.error("Toast container not found!");
                alert(`${type.toUpperCase()}: ${message}`);
                return;
            }

            // Create a unique ID for this message+type combination
            const toastId = `${type}_${message}`;

            // Check if an identical toast is already active
            if (activeToasts.has(toastId)) {
                // Get the existing toast
                const existingToast = activeToasts.get(toastId);

                // Reset its timer
                const bsToast = bootstrap.Toast.getInstance(existingToast);
                if (bsToast) {
                    // Hide and then show to restart the timer
                    bsToast.hide();

                    // Wait for the hide transition to complete
                    setTimeout(() => {
                        existingToast.setAttribute('data-bs-delay', duration);
                        bsToast.show();
                    }, 200);
                }

                return; // Don't create a duplicate
            }

            // Set icon and colors based on notification type
            let iconClass, bgColorClass, textColorClass, title;

            switch (type) {
                case 'success':
                    iconClass = 'bi-check-circle-fill';
                    bgColorClass = 'bg-success';
                    textColorClass = 'text-white';
                    title = 'Success';
                    break;
                case 'error':
                    iconClass = 'bi-x-octagon-fill';
                    bgColorClass = 'bg-danger';
                    textColorClass = 'text-white';
                    title = 'Error';
                    break;
                case 'warning':
                    iconClass = 'bi-exclamation-triangle-fill';
                    bgColorClass = 'bg-warning';
                    textColorClass = 'text-dark';
                    title = 'Warning';
                    break;
                default:
                    iconClass = 'bi-info-circle-fill';
                    bgColorClass = 'bg-primary';
                    textColorClass = 'text-white';
                    title = 'Information';
            }

            // Create the toast element
            const toastEl = document.createElement('div');
            toastEl.className = `toast ${bgColorClass} ${textColorClass} border-0`;
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');
            toastEl.setAttribute('data-bs-delay', duration);
            toastEl.dataset.toastId = toastId; // Store ID for tracking

            // Determine close button style
            const closeButtonClass = type === 'warning' ? 'btn-close-dark' : 'btn-close-white';

            // Build toast HTML content
            toastEl.innerHTML = `
                <div class="toast-header ${type === 'warning' ? 'text-dark' : 'text-white'} bg-transparent border-bottom border-secondary opacity-75">
                    <i class="bi ${iconClass} me-2"></i>
                    <strong class="me-auto">${escapeHTML(title)}</strong>
                    <small>${Math.ceil(duration / 1000)}s</small>
                    <button type="button" class="btn-close ${closeButtonClass}" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    ${escapeHTML(message)}
                </div>
            `;

            toastContainer.appendChild(toastEl);

            // Store the active toast
            activeToasts.set(toastId, toastEl);

            // Initialize and show the toast
            const toast = new bootstrap.Toast(toastEl);

            // Remove from tracking and DOM when hidden
            toastEl.addEventListener('hidden.bs.toast', function() {
                // Remove from active toasts map
                activeToasts.delete(toastId);
                toastEl.remove();
            });

            toast.show();
        }

        /**
         * Processes message content for display, handling markdown, code blocks, etc.
         * @param {string} content - The raw message content
         * @return {string} Processed HTML content
         */
        function processMessageContent(content) {
            if (typeof content !== 'string') return escapeHTML(content);

            // Find and process code blocks
            const codeBlockRegex = /```(\w*)\n([\s\S]*?)```/g;
            let lastIndex = 0;
            let result = '';
            let match;

            while ((match = codeBlockRegex.exec(content)) !== null) {
                // Process text before the code block
                const textBefore = content.substring(lastIndex, match.index);
                result += processRegularText(textBefore);

                // Process the code block
                const language = match[1].trim() || 'plaintext';
                const code = match[2];
                const escapedCode = escapeHTML(code);

                // Create HTML for code block with copy button
                result += `
                    <pre>
                        <div class="code-header">
                            <span class="code-language">${escapeHTML(language)}</span>
                            <button class="copy-btn" title="Copy code">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <code class="language-${escapeHTML(language)}">${escapedCode}</code>
                    </pre>
                `;

                lastIndex = codeBlockRegex.lastIndex;
            }

            // Process any remaining text
            result += processRegularText(content.substring(lastIndex));

            return result;
        }

        /**
         * Process regular text (non-code) portions of messages
         * @param {string} text - Text to process
         * @return {string} Processed HTML
         */
        function processRegularText(text) {
            let processed = escapeHTML(text);

            // Handle line breaks
            processed = processed.replace(/\n\s*\n/g, '</p><p>'); // Double newlines to paragraphs
            processed = processed.replace(/\n/g, '<br>'); // Single newlines to <br>

            // Wrap in paragraphs if needed
            const startsWithBlockTag = /^(<p>|<pre>|<h[1-6]>|<ol>|<ul>|<li>)/i.test(processed.trim());

            if (processed.trim() !== '' && !startsWithBlockTag) {
                processed = '<p>' + processed + '</p>';
            }

            // Clean up empty paragraphs
            processed = processed.replace(/<p><br><\/p>/g, '').replace(/<p>\s*<\/p>/g, '');

            return processed;
        }

        /**
         * Auto-resize the textarea based on content
         */
        function adjustTextareaHeight() {
            userInput.style.height = 'auto';

            const style = window.getComputedStyle(userInput);
            const maxHeight = parseFloat(style.maxHeight) || 200;
            const minHeight = parseFloat(style.lineHeight) * parseInt(userInput.rows, 10) +
                parseFloat(style.paddingTop) + parseFloat(style.paddingBottom) +
                parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);

            let newHeight = userInput.scrollHeight;

            if (newHeight < minHeight) {
                newHeight = minHeight;
            } else if (newHeight > maxHeight) {
                newHeight = maxHeight;
            }

            userInput.style.height = `${newHeight}px`;
        }

        /**
         * Scrolls the message container to the bottom
         */
        function scrollToBottom() {
            setTimeout(() => {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 0);
        }

        /**
         * Shows typing indicator when waiting for AI response
         */
        function showTypingIndicator() {
            removeTypingIndicator();

            const typingWrapper = document.createElement('div');
            typingWrapper.className = 'message-wrapper assistant typing-indicator-wrapper';
            typingWrapper.innerHTML = `
                <div class="typing-indicator">
                    <div class="typing-dots">
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                        <div class="typing-dot"></div>
                    </div>
                </div>`;

            messagesContainer.appendChild(typingWrapper);
            scrollToBottom();
        }

        /**
         * Removes the typing indicator
         */
        function removeTypingIndicator() {
            const indicatorWrapper = messagesContainer.querySelector('.typing-indicator-wrapper');
            if (indicatorWrapper) {
                indicatorWrapper.remove();
            }
        }

        /**
         * Updates the send button state based on input and sending status
         */
        function updateSendButtonState() {
            sendBtn.disabled = userInput.value.trim().length === 0 || isSending;
        }

        // ======== Storage Management ========

        /**
         * Loads chat data from localStorage
         */
        function loadChats() {
            chats = JSON.parse(localStorage.getItem('chats')) || {};
            currentChatId = localStorage.getItem('currentChatId');

            // Ensure all chats have required properties
            for (const chatId in chats) {
                if (chats.hasOwnProperty(chatId)) {
                    // Add model property if missing
                    if (!chats[chatId].hasOwnProperty('model')) {
                        chats[chatId].model = defaultModel;
                    }

                    // Validate model is available
                    const isModelAvailable = availableModels.some(model => model.id === chats[chatId].model);
                    if (!isModelAvailable) {
                        console.warn(`Model "${chats[chatId].model}" is not available. Setting to default.`);
                        chats[chatId].model = defaultModel;
                    }

                    // Ensure updated timestamp exists
                    if (!chats[chatId].hasOwnProperty('updated')) {
                        chats[chatId].updated = chats[chatId].created || new Date().toISOString();
                    }
                }
            }

            saveChats();
        }

        /**
         * Saves chat data to localStorage
         */
        function saveChats() {
            try {
                localStorage.setItem('chats', JSON.stringify(chats));
                localStorage.setItem('currentChatId', currentChatId);
            } catch (e) {
                console.error("Failed to save chats:", e);
                showToast("Error saving chat history. Local storage may be full.", 'error', 7000);
            }
        }

        // ======== Chat Session Management ========

        /**
         * Creates a new chat session
         * @param {boolean} switchUI - Whether to switch to the new chat immediately
         */
        function createNewChat(switchUI = true) {
            const chatId = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const timestamp = new Date().toISOString();
            const selectedModel = modelSelect.value || defaultModel;

            const newChat = {
                id: chatId,
                title: 'New Chat',
                created: timestamp,
                updated: timestamp,
                messages: [],
                model: selectedModel
            };

            chats[chatId] = newChat;
            currentChatId = chatId;

            saveChats();
            renderChatList();

            if (switchUI) {
                switchChat(chatId);
                userInput.value = '';
                adjustTextareaHeight();
                updateSendButtonState();
                userInput.focus();

                if (window.innerWidth < 768) {
                    hideSidebar();
                }

                showToast("New chat created.", 'success', 2000);
            }
        }

        /**
         * Switches the UI to display a specific chat
         * @param {string} chatId - ID of the chat to switch to
         */
        function switchChat(chatId) {
            // Validate chat exists
            if (!chatId || !chats[chatId]) {
                console.error("Attempted to switch to non-existent chat:", chatId);
                showToast("Error: Chat not found. Switching to most recent chat.", 'error', 4000);

                const sortedChatIds = Object.keys(chats).sort((a, b) =>
                    new Date(chats[b]?.updated || 0) - new Date(chats[a]?.updated || 0)
                );

                if (sortedChatIds.length > 0) {
                    switchChat(sortedChatIds[0]);
                } else {
                    console.log("No chats available, creating a new one.");
                    createNewChat();
                }
                return;
            }

            // Skip if already on this chat
            if (chatId === currentChatId) {
                if (window.innerWidth < 768) {
                    hideSidebar();
                }
                return;
            }

            // Update current chat ID
            currentChatId = chatId;
            localStorage.setItem('currentChatId', currentChatId);

            // Update UI
            document.querySelectorAll('.chat-item').forEach(item => {
                item.classList.toggle('active', item.dataset.chatId === chatId);
            });

            currentChatTitle.textContent = getChatDisplayTitle(chats[currentChatId]);
            modelSelect.value = chats[currentChatId].model || defaultModel;

            renderMessages();

            userInput.disabled = false;
            userInput.focus();
            updateSendButtonState();

            if (window.innerWidth < 768) {
                hideSidebar();
            }
        }

        /**
         * Deletes a chat session
         * @param {string} chatIdToDelete - ID of chat to delete
         */
        function deleteChat(chatIdToDelete) {
            if (!chatIdToDelete || !chats[chatIdToDelete]) {
                console.warn("Attempted to delete non-existent chat:", chatIdToDelete);
                showToast("Error: Chat not found for deletion.", 'warning', 3000);
                return;
            }

            const chatTitle = getChatDisplayTitle(chats[chatIdToDelete]);

            // Confirm deletion
            if (!confirm(
                    `Are you sure you want to delete "${escapeHTML(chatTitle)}"? This cannot be undone.`)) {
                return;
            }

            const isCurrentChat = chatIdToDelete === currentChatId;

            // Delete the chat
            delete chats[chatIdToDelete];

            // Handle current chat deletion
            if (isCurrentChat) {
                const sortedChatIds = Object.keys(chats).sort((a, b) =>
                    new Date(chats[b]?.updated || 0) - new Date(chats[a]?.updated || 0)
                );

                if (sortedChatIds.length > 0) {
                    switchChat(sortedChatIds[0]);
                } else {
                    createNewChat();
                }
            } else {
                saveChats();
                renderChatList();
            }

            showToast(`Chat "${escapeHTML(chatTitle)}" deleted.`, 'success', 3000);
        }

        /**
         * Clears messages from the current chat
         */
        function clearCurrentChatMessages() {
            if (!currentChatId || !chats[currentChatId]) {
                showToast("No active chat to clear.", 'warning', 3000);
                return;
            }

            if (!chats[currentChatId].messages || chats[currentChatId].messages.length === 0) {
                showToast("Chat is already empty.", 'info', 3000);
                return;
            }

            // Confirm clearing
            if (!confirm('Are you sure you want to clear all messages? This cannot be undone.')) {
                return;
            }

            // Clear messages and reset title
            chats[currentChatId].messages = [];
            chats[currentChatId].title = 'New Chat';
            chats[currentChatId].updated = new Date().toISOString();

            saveChats();
            renderMessages();
            renderChatList();

            currentChatTitle.textContent = 'New Chat';
            userInput.focus();

            showToast("Chat cleared successfully.", 'success', 3000);
        }

        /**
         * Exports current chat as JSON file
         */
        function exportChat() {
            if (!currentChatId || !chats[currentChatId]) {
                showToast("No chat selected to export.", 'warning');
                return;
            }

            if (!chats[currentChatId].messages || chats[currentChatId].messages.length === 0) {
                showToast("Chat is empty, nothing to export.", 'info', 3000);
                return;
            }

            try {
                const chatToExport = {
                    ...chats[currentChatId]
                };
                const chatData = JSON.stringify(chatToExport, null, 2);
                const blob = new Blob([chatData], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);

                // Create download link
                const a = document.createElement('a');
                a.href = url;

                // Create safe filename
                const filenameTitle = (getChatDisplayTitle(chats[currentChatId]) || 'chat')
                    .replace(/[^a-z0-9\s]/gi, '')
                    .trim().replace(/\s+/g, '_')
                    .toLowerCase().substring(0, 30);

                const filenameIdPart = currentChatId.replace('chat_', '').split('_')[0];
                a.download = `${filenameTitle || 'chat'}_${filenameIdPart}.json`;

                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);

                showToast("Chat exported successfully!", 'success', 4000);
            } catch (error) {
                console.error("Failed to export chat:", error);
                showToast(`Failed to export chat: ${escapeHTML(error.message || 'Unknown error')}`, 'error',
                    5000);
            }
        }

        /**
         * Gets the display title for a chat
         * @param {Object} chat - The chat object
         * @return {string} Display title
         */
        function getChatDisplayTitle(chat) {
            if (chat && chat.title && chat.title !== 'New Chat') {
                return chat.title;
            }

            const firstUserMessage = chat?.messages?.find(m => m.role === 'user');
            if (firstUserMessage?.content) {
                return generateChatTitle(firstUserMessage.content);
            }

            return 'New Chat';
        }

        /**
         * Generates a title from message content
         * @param {string} messageContent - Content to generate title from 
         * @return {string} Generated title
         */
        function generateChatTitle(messageContent) {
            const maxLength = 30;

            if (typeof messageContent !== 'string' || !messageContent.trim()) {
                return 'Chat';
            }

            // Take first line and clean it up
            let title = messageContent.trim().split('\n')[0];
            title = title.replace(/^(\s*[-*+]|\s*\d+\.)*\s*/, '');
            title = title.replace(/```.*?```/g, '').trim();

            // Truncate if needed
            if (title.length > maxLength) {
                title = title.substring(0, maxLength).trim() + '...';
            }

            return title || 'Chat';
        }

        // ======== UI Rendering ========

        /**
         * Populates the model selection dropdown
         */
        function populateModelSelect() {
            modelSelect.innerHTML = '';

            availableModels.forEach(model => {
                const option = document.createElement('option');
                option.value = model.id;
                option.textContent = model.name;
                modelSelect.appendChild(option);
            });

            modelSelect.value = chats[currentChatId]?.model || defaultModel;
        }

        /**
         * Renders the chat list in the sidebar
         */
        function renderChatList() {
            chatList.innerHTML = '';

            // Sort chats by updated timestamp (newest first)
            const sortedChatIds = Object.keys(chats).sort((a, b) => {
                const dateA = new Date(chats[a]?.updated || 0);
                const dateB = new Date(chats[b]?.updated || 0);
                return dateB.getTime() - dateA.getTime();
            });

            sortedChatIds.forEach(chatId => {
                const chat = chats[chatId];
                if (!chat) return;

                const chatItem = document.createElement('div');
                chatItem.className = `chat-item ${chatId === currentChatId ? 'active' : ''}`;
                chatItem.dataset.chatId = chatId;

                const displayTitle = getChatDisplayTitle(chat);

                chatItem.innerHTML = `
                    <i class="bi bi-chat-left-text chat-item-icon"></i>
                    <span class="chat-item-title">${escapeHTML(displayTitle)}</span>
                    <button class="btn btn-sm btn-link text-danger delete-chat-btn p-0 ms-auto" data-chat-id="${chatId}" title="Delete chat">
                        <i class="bi bi-x-circle"></i>
                    </button>
                `;

                // Add click handler to switch chats
                chatItem.addEventListener('click', (e) => {
                    if (e.target.closest('.delete-chat-btn')) {
                        return;
                    }
                    switchChat(chatId);
                });

                // Add delete button handler
                const deleteButton = chatItem.querySelector('.delete-chat-btn');
                if (deleteButton) {
                    deleteButton.addEventListener('click', (e) => {
                        e.stopPropagation();
                        const idToDelete = e.currentTarget.dataset.chatId;
                        deleteChat(idToDelete);
                    });
                }

                chatList.appendChild(chatItem);
            });
        }

        /**
         * Renders all messages for the current chat
         */
        function renderMessages() {
            messagesContainer.innerHTML = '';

            // Check if we have a chat and messages
            if (!currentChatId || !chats[currentChatId]?.messages) {
                messagesContainer.innerHTML =
                    "<p class='text-center text-secondary mt-5'>Start a conversation by typing a message below.</p>";
                return;
            }

            // Check if there are any messages
            if (chats[currentChatId].messages.length === 0) {
                messagesContainer.innerHTML =
                    "<p class='text-center text-secondary mt-5'>Start a conversation by typing a message below.</p>";
                return;
            }

            // Display each message
            chats[currentChatId].messages.forEach(message => {
                displayMessage(message);
            });

            scrollToBottom();
        }

        /**
         * Displays a single message
         * @param {Object} message - Message object to display
         * @param {boolean} prepend - If true, prepends to container instead of appending
         * @param {boolean} animate - If true, shows typing animation for assistant messages
         */
        function displayMessage(message, prepend = false, animate = false) {
            // Validate message
            if (!message || !message.role || typeof message.content === 'undefined') {
                console.warn("Skipping invalid message:", message);
                return;
            }

            // Remove placeholder if present
            if (messagesContainer.querySelector('.text-center.text-secondary.mt-5')) {
                messagesContainer.innerHTML = '';
            }

            // Create message wrapper
            const messageWrapper = document.createElement('div');
            messageWrapper.className = `message-wrapper ${message.role}`;
            messageWrapper.dataset.messageId = message.id;

            // Format timestamp
            let timestampText = '';
            try {
                if (message.timestamp && !isNaN(new Date(message.timestamp).getTime())) {
                    const date = new Date(message.timestamp);
                    timestampText = date.toLocaleTimeString([], {
                        hour: 'numeric',
                        minute: '2-digit'
                    });
                }
            } catch (e) {
                console.error("Error formatting timestamp:", e, message.timestamp);
                timestampText = '';
            }

            // Create message bubble
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${message.role}`;

            // For assistant messages with animation, start with empty content
            // Otherwise, process and show the full content
            if (message.role === 'assistant' && animate) {
                messageDiv.innerHTML = `<div class="message-content typing-text"></div>`;
            } else {
                const processedContent = processMessageContent(message.content);
                messageDiv.innerHTML = `<div class="message-content">${processedContent}</div>`;
            }

            // Create metadata section
            const metaDiv = document.createElement('div');
            metaDiv.className = 'message-meta';

            const timestampSpan = document.createElement('span');
            timestampSpan.className = 'message-timestamp';
            timestampSpan.textContent = timestampText;
            metaDiv.appendChild(timestampSpan);

            // Add action buttons for user messages
            if (message.role === 'user') {
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'message-actions';

                // Edit button
                const editBtn = document.createElement('button');
                editBtn.className = 'action-btn edit-btn';
                editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
                editBtn.title = 'Edit Message';
                editBtn.dataset.messageId = message.id;
                editBtn.addEventListener('click', (e) => {
                    const msgId = e.currentTarget.dataset.messageId;
                    const msg = chats[currentChatId]?.messages.find(m => m.id === msgId);
                    if (msg) handleEditClick(msg.id, msg.content);
                });
                actionsDiv.appendChild(editBtn);

                // Delete button
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'action-btn delete-btn';
                deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                deleteBtn.title = 'Delete Message and Responses';
                deleteBtn.dataset.messageId = message.id;
                deleteBtn.addEventListener('click', (e) => {
                    const msgId = e.currentTarget.dataset.messageId;
                    handleDeleteClick(msgId);
                });
                actionsDiv.appendChild(deleteBtn);

                metaDiv.appendChild(actionsDiv);
            }

            // Add elements to wrapper
            messageWrapper.appendChild(messageDiv);
            messageWrapper.appendChild(metaDiv);

            // Add to container
            if (prepend) {
                messagesContainer.prepend(messageWrapper);
            } else {
                messagesContainer.appendChild(messageWrapper);
            }

            // If this is an assistant message with animation, simulate typing
            if (message.role === 'assistant' && animate) {
                const contentDiv = messageDiv.querySelector('.message-content');
                const processedContent = processMessageContent(message.content);

                // Start typing animation
                simulateTyping(contentDiv, processedContent);
            } else {
                // Apply syntax highlighting for code blocks immediately 
                applySyntaxHighlighting(messageWrapper);
            }

            // Scroll to bottom for new messages
            if (!prepend) {
                scrollToBottom();
            }
        }

        /**
         * Simulates typing animation for the AI response
         * @param {HTMLElement} element - The element to animate
         * @param {string} content - The HTML content to type
         */
        function simulateTyping(element, content) {
            // First extract code blocks so we can animate them differently
            const blocks = [];
            let plainContent = content;

            // Extract code blocks and replace them with placeholders
            plainContent = plainContent.replace(/<pre[\s\S]*?<\/pre>/g, (match) => {
                blocks.push(match);
                return `[CODE_BLOCK_${blocks.length - 1}]`;
            });

            // Calculate a realistic typing speed (characters per second)
            // Faster for regular text, slower for code and complex content
            const baseSpeed = 50; // characters per second

            // Current typed content
            let currentText = '';

            // Element to track typing position
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = plainContent;
            const textContent = tempDiv.textContent;
            const textLength = textContent.length;

            // For staggering typing speed to seem more human
            const pauseChance = 0.03; // 3% chance of a pause at any character
            const pauseDuration = 150; // ms

            // Character index counter
            let charIndex = 0;

            // Segment index (for handling code blocks)
            let segmentIndex = 0;
            let segments = plainContent.split(/(\[CODE_BLOCK_\d+\])/);

            // Start typing animation
            function typeNextSegment() {
                if (segmentIndex >= segments.length) {
                    // Finished typing all segments
                    element.classList.remove('typing-text');

                    // Apply syntax highlighting after typing is complete
                    const messageWrapper = element.closest('.message-wrapper');
                    applySyntaxHighlighting(messageWrapper);
                    return;
                }

                const segment = segments[segmentIndex];

                // Check if this is a code block placeholder
                if (segment.match(/\[CODE_BLOCK_(\d+)\]/)) {
                    const blockIndex = parseInt(segment.match(/\[CODE_BLOCK_(\d+)\]/)[1]);
                    const codeBlock = blocks[blockIndex];

                    // Insert the code block all at once
                    currentText += codeBlock;
                    element.innerHTML = currentText;

                    // Move to next segment
                    segmentIndex++;

                    // Add a pause before continuing to the next segment
                    setTimeout(typeNextSegment, 500); // longer pause after code block
                } else {
                    // Type this segment character by character
                    let segmentCharIndex = 0;

                    function typeNextChar() {
                        if (segmentCharIndex >= segment.length) {
                            // Finished typing this segment
                            segmentIndex++;
                            typeNextSegment();
                            return;
                        }

                        // Add next character
                        currentText += segment[segmentCharIndex];
                        element.innerHTML = currentText;
                        segmentCharIndex++;

                        // Scroll to keep the latest text in view
                        scrollToBottom();

                        // Randomize typing speed slightly for a more human effect
                        let delay = 1000 / (baseSpeed * (0.8 + Math.random() * 0.4));

                        // Occasionally add a longer pause (simulates thinking)
                        if (Math.random() < pauseChance) {
                            delay += pauseDuration;
                        }

                        // Schedule next character
                        setTimeout(typeNextChar, delay);
                    }

                    typeNextChar();
                }
            }

            // Start the typing animation
            typeNextSegment();
        }

        /**
         * Applies syntax highlighting to code blocks
         * @param {HTMLElement} messageWrapper - The message wrapper element
         */
        function applySyntaxHighlighting(messageWrapper) {
            requestAnimationFrame(() => {
                // Highlight code blocks
                messageWrapper.querySelectorAll('pre code').forEach(block => {
                    if (!block.classList.contains('hljs')) {
                        try {
                            const lang = block.classList[0]?.replace('language-', '') || '';
                            if (lang && hljs.getLanguage(lang)) {
                                hljs.highlightElement(block);
                            } else {
                                hljs.highlightElement(block);
                            }
                        } catch (error) {
                            console.error("Highlight.js error:", error);
                        }
                    }
                });

                // Add copy button listeners
                messageWrapper.querySelectorAll('.copy-btn').forEach(btn => {
                    if (!btn.dataset.copyListenerAdded) {
                        btn.addEventListener('click', handleCopyCode);
                        btn.dataset.copyListenerAdded = 'true';
                    }
                });
            });
        }

        // ======== Message Actions ========

        /**
         * Handles clicking the edit button on a message
         * @param {string} messageId - ID of message to edit
         * @param {string} currentContent - Current message content
         */
        function handleEditClick(messageId, currentContent) {
            editingMessageId = messageId;
            editMessageText.value = currentContent;
            editModal.show();
        }

        /**
         * Handles saving edited message and regenerating response
         */
        function handleSaveEdit() {
            const newContent = editMessageText.value.trim();

            if (!newContent || !editingMessageId || !currentChatId) {
                showToast("No content or message selected for editing.", 'warning', 3000);
                return;
            }

            const chat = chats[currentChatId];
            const messageIndex = chat.messages.findIndex(m => m.id === editingMessageId);

            if (messageIndex === -1 || chat.messages[messageIndex].role !== 'user') {
                console.error("Cannot edit non-existent or non-user message:", editingMessageId);
                showToast("Error: Cannot edit this message.", 'error', 3000);
                return;
            }

            // Update message
            chat.messages[messageIndex].content = newContent;
            chat.messages[messageIndex].timestamp = new Date().toISOString();

            // Remove subsequent messages
            chat.messages.splice(messageIndex + 1);

            // Update chat timestamp
            chat.updated = new Date().toISOString();

            // Update title if needed
            const firstUserMessageIndex = chat.messages.findIndex(m => m.role === 'user');
            if (messageIndex === firstUserMessageIndex || chat.title === 'New Chat') {
                chat.title = generateChatTitle(chat.messages[firstUserMessageIndex]?.content || '');
                currentChatTitle.textContent = getChatDisplayTitle(chat);
                renderChatList();
            }

            saveChats();
            editModal.hide();
            renderMessages();

            showToast("Edit saved. Regenerating response...", 'info', 2500);

            // Send edited message to get new response
            sendMessageToServer();
        }

        /**
         * Handles message deletion
         * @param {string} messageId - ID of message to delete
         */
        function handleDeleteClick(messageId) {
            if (!currentChatId) {
                console.warn("No active chat for deletion.");
                return;
            }

            const chat = chats[currentChatId];
            const messageIndex = chat.messages.findIndex(m => m.id === messageId);

            if (messageIndex === -1) {
                console.warn("Message not found:", messageId);
                showToast("Error: Message not found.", 'warning', 3000);
                return;
            }

            const isUserMessage = chat.messages[messageIndex].role === 'user';

            // Confirm deletion
            let confirmMsg = 'Are you sure you want to delete this message?';
            if (isUserMessage) {
                confirmMsg += ' This will also delete all subsequent messages.';
            }

            if (!confirm(confirmMsg)) {
                return;
            }

            // Delete message(s)
            if (isUserMessage) {
                chat.messages.splice(messageIndex); // Remove this and all following messages
            } else {
                chat.messages.splice(messageIndex, 1); // Remove just this message
            }

            chat.updated = new Date().toISOString();

            // Reset title if first user message was deleted
            const firstUserMessage = chat.messages.find(m => m.role === 'user');
            if (!firstUserMessage && chat.title !== 'New Chat') {
                chat.title = 'New Chat';
                currentChatTitle.textContent = chat.title;
                renderChatList();
            }

            saveChats();
            renderMessages();
            updateSendButtonState();

            showToast("Message deleted.", 'success', 2000);
        }

        /**
         * Handles copying code from code blocks
         */
        async function handleCopyCode() {
            const button = this;
            const pre = button.closest('pre');
            const codeElement = pre?.querySelector('code');

            if (!codeElement) {
                console.error("Could not find code element to copy.");
                showToast("Error: Could not find code to copy.", 'error', 3000);
                return;
            }

            const codeToCopy = codeElement.textContent || '';

            try {
                await navigator.clipboard.writeText(codeToCopy);

                // Visual feedback
                const originalIconHTML = button.innerHTML;
                button.innerHTML = '<i class="bi bi-check2"></i> Copied!';
                button.classList.add('copied');

                setTimeout(() => {
                    button.innerHTML = originalIconHTML;
                    button.classList.remove('copied');
                }, 2000);

            } catch (err) {
                console.error('Failed to copy code:', err);

                if (err.name === 'NotAllowedError' || err.name === 'SecurityError') {
                    showToast('Failed to copy: Clipboard permission denied.', 'error', 7000);
                } else {
                    showToast('Failed to copy code to clipboard.', 'warning', 3000);
                }
            }
        }

        // ======== Message Sending ========

        /**
         * Handles form submission when user sends a message
         * @param {Event} event - Form submission event
         */
        async function handleFormSubmit(event) {
            if (event) event.preventDefault();
            if (isSending) return;

            const messageContent = userInput.value.trim();
            if (!messageContent) {
                updateSendButtonState();
                return;
            }

            // Ensure a chat is active
            if (!currentChatId || !chats[currentChatId]) {
                console.error("No active chat when sending message.");
                showToast("Error: No active chat found. Creating a new one.", 'error', 6000);

                if (Object.keys(chats).length === 0) {
                    createNewChat(true);
                }
                return;
            }

            const timestamp = new Date().toISOString();

            // Create user message
            const userMessage = {
                id: 'msg_user_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5),
                role: 'user',
                content: messageContent,
                timestamp: timestamp
            };

            // Add to chat
            chats[currentChatId].messages.push(userMessage);
            chats[currentChatId].updated = timestamp;

            // Update title for first message
            const userMessagesInChat = chats[currentChatId].messages.filter(m => m.role === 'user');
            if (userMessagesInChat.length === 1 && chats[currentChatId].title === 'New Chat') {
                chats[currentChatId].title = generateChatTitle(messageContent);
                currentChatTitle.textContent = getChatDisplayTitle(chats[currentChatId]);
                renderChatList();
            }

            saveChats();
            displayMessage(userMessage);

            userInput.value = '';
            adjustTextareaHeight();
            updateSendButtonState();

            await sendMessageToServer();
        }

        /**
         * Sends messages to the server to get AI response
         */
        async function sendMessageToServer() {
            if (isSending || !currentChatId || !chats[currentChatId]) {
                console.warn("Invalid state for sending message.");
                return;
            }

            const modelId = modelSelect.value;

            if (!modelId || modelId === "null") {
                showToast("Please select an AI model.", 'warning', 3000);
                return;
            }

            isSending = true;
            userInput.disabled = true;
            sendBtn.disabled = true;
            showTypingIndicator();

            // Prepare messages for API
            const contextMessages = chats[currentChatId].messages.map(msg => ({
                role: msg.role,
                content: msg.content
            }));

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        messages: contextMessages,
                        model: modelId
                    })
                });

                removeTypingIndicator();

                if (!response.ok) {
                    // Handle HTTP errors
                    let errorMsg = `HTTP Error: ${response.status} ${response.statusText}`;

                    try {
                        const errorData = await response.json();
                        const errorDetails = errorData.error?.message ?? errorData.message ?? JSON
                            .stringify(errorData);
                        errorMsg = errorDetails;
                    } catch (parseError) {
                        console.error("Failed to parse error response:", parseError);
                        errorMsg = `HTTP Error: ${response.status} ${response.statusText}`;
                    }

                    showToast(`API Request Failed: ${escapeHTML(errorMsg)}`, 'error', 7000);
                } else {
                    // Process successful response
                    const data = await response.json();

                    if (data.error) {
                        const apiErrorMsg = typeof data.error === 'string' ? data.error :
                            (data.error.message ?? JSON.stringify(data.error));

                        console.error("API error in response body:", data.error);
                        showToast(`API Error: ${escapeHTML(apiErrorMsg)}`, 'error', 7000);
                    } else {
                        const aiContent = data.choices?. [0]?.message?.content;

                        if (!aiContent) {
                            console.warn("API returned no content:", data);
                            showToast("Received an empty response from the AI.", 'warning', 5000);
                        } else {
                            // Create and add assistant message
                            const assistantMessage = {
                                id: 'msg_assistant_' + Date.now() + '_' + Math.random().toString(36)
                                    .substr(2, 5),
                                role: 'assistant',
                                content: aiContent,
                                timestamp: new Date().toISOString()
                            };

                            chats[currentChatId].messages.push(assistantMessage);
                            chats[currentChatId].updated = assistantMessage.timestamp;

                            saveChats();
                            // Display with typing animation
                            displayMessage(assistantMessage, false, true);
                        }
                    }
                }
            } catch (error) {
                // Handle network errors
                console.error('Network or unexpected error:', error);
                removeTypingIndicator();

                showToast(
                    `Network error: ${escapeHTML(error.message || 'Unknown error during request.')}`,
                    'error', 7000);
            } finally {
                // Clean up state
                isSending = false;
                userInput.disabled = false;
                updateSendButtonState();
                userInput.focus();
            }
        }

        // ======== Mobile Sidebar Functions ========

        /**
         * Sets up the sidebar overlay
         */
        function setupSidebarOverlay() {
            sidebarOverlay.addEventListener('click', hideSidebar);
        }

        /**
         * Shows the sidebar on mobile
         */
        function showSidebar() {
            if (window.innerWidth < 768) {
                sidebar.classList.add('show');
                sidebarOverlay.style.display = 'block';

                requestAnimationFrame(() => {
                    sidebarOverlay.style.opacity = '1';
                });

                document.body.style.overflow = 'hidden';
                document.body.classList.add('sidebar-open');
            }
        }

        /**
         * Hides the sidebar on mobile
         */
        function hideSidebar() {
            if (window.innerWidth < 768) {
                sidebar.classList.remove('show');
                sidebarOverlay.style.opacity = '0';

                setTimeout(() => {
                    sidebarOverlay.style.display = 'none';
                    document.body.style.overflow = '';
                    document.body.classList.remove('sidebar-open');
                }, 300);
            }
        }

        // ======== Event Listeners ========

        /**
         * Sets up all event listeners
         */
        function setupEventListeners() {
            // Form submission
            messageForm.addEventListener('submit', handleFormSubmit);

            // Input changes
            userInput.addEventListener('input', () => {
                adjustTextareaHeight();
                updateSendButtonState();
            });

            // Enter key handling
            userInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey && userInput.value.trim().length > 0 && !
                    isSending) {
                    e.preventDefault();
                    handleFormSubmit();
                }
            });

            // Button clicks
            newChatBtn.addEventListener('click', () => createNewChat());
            clearChatBtn.addEventListener('click', clearCurrentChatMessages);
            if (clearChatBtnMobile) {
                clearChatBtnMobile.addEventListener('click', clearCurrentChatMessages);
            }

            exportChatBtn.addEventListener('click', exportChat);
            if (exportChatBtnMobile) {
                exportChatBtnMobile.addEventListener('click', exportChat);
            }

            // Model selection
            modelSelect.addEventListener('change', handleModelChange);

            // Edit modal
            saveEditBtn.addEventListener('click', handleSaveEdit);

            // Mobile sidebar
            openSidebarBtn.addEventListener('click', showSidebar);
            closeSidebarBtn.addEventListener('click', hideSidebar);

            // Window resize
            window.addEventListener('resize', () => {
                adjustTextareaHeight();

                if (window.innerWidth >= 768) {
                    hideSidebar();
                } else if (sidebar.classList.contains('show')) {
                    sidebarOverlay.style.display = 'block';
                }
            });

            // Modal hidden event
            editModalEl.addEventListener('hidden.bs.modal', () => {
                editingMessageId = null;
                editMessageText.value = '';
            });

            updateSendButtonState();
        }

        /**
         * Handles model selection change by creating a new chat with the selected model
         */
        function handleModelChange() {
            const newModel = modelSelect.value;
            if (!newModel || newModel === "null") {
                showToast("Invalid model selected.", 'warning', 3000);
                modelSelect.value = chats[currentChatId]?.model || defaultModel;
                return;
            }

            // Validate model
            const isModelAvailable = availableModels.some(model => model.id === newModel);
            if (!isModelAvailable) {
                console.warn("Selected model is not in the available list:", newModel);
                showToast(`Warning: Model "${newModel}" might not be officially supported.`, 'warning', 5000);
            }

            // Get the selected model name for display
            const selectedModelName = modelSelect.options[modelSelect.selectedIndex]?.text || newModel;

            // Create a new chat with the selected model
            const chatId = 'chat_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const timestamp = new Date().toISOString();

            const newChat = {
                id: chatId,
                title: `New ${selectedModelName} Chat`,
                created: timestamp,
                updated: timestamp,
                messages: [],
                model: newModel
            };

            chats[chatId] = newChat;
            currentChatId = chatId;

            saveChats();
            renderChatList();
            switchChat(chatId);

            showToast(`Created new chat with ${escapeHTML(selectedModelName)}.`, 'success', 3000);
        }

        // ======== Initialization ========

        /**
         * Initializes the application
         */
        function init() {
            loadChats();

            // Determine initial chat to load
            let initialChatId = currentChatId;

            const chatIds = Object.keys(chats);
            if (chatIds.length === 0) {
                // Create new chat if none exist
                console.log("No chats found. Creating a new one.");
                createNewChat(false);
                initialChatId = currentChatId;
            } else if (!initialChatId || !chats[initialChatId]) {
                // Find most recent chat if current is invalid
                console.log("Invalid chat ID. Selecting most recent.");
                const sortedChatIds = chatIds.sort((a, b) =>
                    new Date(chats[b]?.updated || 0) - new Date(chats[a]?.updated || 0)
                );
                initialChatId = sortedChatIds[0];
                localStorage.setItem('currentChatId', initialChatId);
            }

            // Final validation
            if (!initialChatId || !chats[initialChatId]) {
                console.error("Initialization failed: No valid chat.");
                showToast("Failed to initialize. Please try refreshing the page.", 'error', 10000);
                userInput.disabled = true;
                sendBtn.disabled = true;
                messagesContainer.innerHTML =
                    "<p class='text-center text-secondary mt-5'>Error loading application. Please refresh the page.</p>";
                return;
            }

            // Set up the application
            populateModelSelect();
            renderChatList();
            switchChat(initialChatId);
            setupEventListeners();
            adjustTextareaHeight();
            setupSidebarOverlay();
            userInput.focus();

            console.log("App initialized. Current chat:", currentChatId, "Model:", modelSelect.value);
        }

        // Start the application
        init();
    });

    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });

    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && (e.key === 'u' || e.key === 's' || e.keyCode === 123)) {
            e.preventDefault();
        }
    });
    </script>
</body>

</html>