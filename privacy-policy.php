<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chính Sách Bảo Mật - HaUI Chatbot</title>
    <style>
        :root {
            --primary: #1877f2;
            --dark: #0f172a;
            --gray: #475569;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { color: var(--dark); background: #f8fafc; line-height: 1.7; }
        
        header { background: #fff; padding: 1.2rem 8%; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.15rem; font-weight: 700; color: var(--primary); text-decoration: none; }
        nav a { color: var(--gray); text-decoration: none; font-size: 0.95rem; }
        nav a:hover { color: var(--primary); }

        .content { max-width: 800px; margin: 2rem auto; background: #fff; padding: 2.5rem; border-radius: 8px; border: 1px solid var(--border); }
        h1 { font-size: 1.8rem; margin-bottom: 0.5rem; color: #0f172a; }
        .date { font-size: 0.9rem; color: var(--gray); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; }
        h2 { font-size: 1.2rem; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #0f172a; }
        p, ul { color: var(--gray); font-size: 0.95rem; margin-bottom: 0.85rem; }
        ul { padding-left: 1.4rem; }
        li { margin-bottom: 0.35rem; }

        .highlight-box { background: #f0f7ff; border-left: 4px solid var(--primary); padding: 1rem; border-radius: 4px; margin: 1.5rem 0; }
        .highlight-box p { color: #1e40af; margin-bottom: 0; font-size: 0.95rem; }

        footer { text-align: center; padding: 2rem; color: #94a3b8; font-size: 0.85rem; }
    </style>
</head>
<body>

    <header>
        <a href="/" class="logo">🤖 HaUI Chatbot</a>
        <nav>
            <a href="/">&larr; Quay lại trang chủ</a>
        </nav>
    </header>

    <div class="content">
        <h1>Chính Sách Quyền Riêng Tư</h1>
        <p class="date">Cập nhật lần cuối: Tháng <?php echo date("m/Y"); ?></p>

        <p>Hệ thống <strong>HaUI Chatbot</strong> được thiết lập nhằm hỗ trợ tự động trả lời và xử lý các yêu cầu của người dùng nhắn tin đến Fanpage chính thức <strong>HaUI Chatbot</strong> (<a href="https://web.facebook.com/HauiChatBot.Fanpage/" target="_blank">fb.com/HauiChatBot.Fanpage</a>). Chúng tôi cam kết bảo vệ dữ liệu cá nhân theo đúng chính sách của Meta Developer Platform.</p>

        <h2>1. Dữ Liệu Thu Thập</h2>
        <p>Khi bạn gửi tin nhắn tới Fanpage của chúng tôi, hệ thống tiếp nhận qua Facebook Webhook:</p>
        <ul>
            <li><strong>Page-Scoped ID (PSID):</strong> Dãy số nhận diện ẩn danh do Meta cấp riêng cho cuộc trò chuyện giữa bạn và Fanpage.</li>
            <li><strong>Nội dung tin nhắn:</strong> Văn bản câu hỏi mà bạn gửi đến để bot phân tích và phản hồi.</li>
            <li><strong>Tên hiển thị công khai:</strong> Nhằm mục đích xưng hô trong nội dung tin nhắn hỗ trợ.</li>
        </ul>

        <h2>2. Mục Đích Sử Dụng</h2>
        <ul>
            <li>Gửi lại tin nhắn phản hồi tự động theo nội dung câu hỏi của người dùng.</li>
            <li>Cải thiện độ chính xác của các câu trả lời tự động.</li>
            <li><strong>Tuyệt đối không:</strong> Chia sẻ, buôn bán dữ liệu hoặc sử dụng thông tin của bạn vào mục đích quảng cáo rác.</li>
        </ul>

        <h2>3. Lưu Trữ & Bảo Mật</h2>
        <p>Mọi kết nối từ Meta tới server backend đều chạy qua giao thức bảo mật HTTPS/SSL. Dữ liệu tin nhắn chỉ được lưu tạm thời phục vụ luồng phản hồi hỗ trợ.</p>

        <h2 id="data-deletion">4. Hướng Dẫn Yêu Cầu Xóa Dữ Liệu (Data Deletion)</h2>
        <div class="highlight-box">
            <p><strong>Quyền xóa bỏ dữ liệu:</strong> Người dùng có thể yêu cầu xóa hoàn toàn lịch sử tương tác và thông tin định danh bất kỳ lúc nào.</p>
        </div>
        <p>Để xóa dữ liệu, bạn thực hiện một trong các cách sau:</p>
        <ul>
            <li><strong>Cách 1 (Tự động):</strong> Soạn tin nhắn với nội dung <code>#DELETE</code> hoặc <code>#XOA_DU_LIEU</code> gửi trực tiếp vào <a href="https://web.facebook.com/HauiChatBot.Fanpage/" target="_blank">Fanpage HaUI Chatbot</a>. Hệ thống sẽ tự động hủy toàn bộ bản ghi liên quan đến PSID của bạn.</li>
            <li><strong>Cách 2 (Thủ công):</strong> Gửi yêu cầu qua email hỗ trợ của chúng tôi bên dưới để được nhân viên kỹ thuật hỗ trợ xóa dữ liệu trong vòng 24 giờ.</li>
        </ul>

        <h2>5. Liên Hệ Hỗ Trợ</h2>
        <p>Nếu có bất kỳ thắc mắc nào về chính sách này, xin vui lòng liên hệ:</p>
        <ul>
            <li><strong>Fanpage:</strong> <a href="https://web.facebook.com/HauiChatBot.Fanpage/" target="_blank">https://web.facebook.com/HauiChatBot.Fanpage/</a></li>
            <li><strong>Email:</strong> hauichatbot@lamoki.com</li>
        </ul>
    </div>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> HaUI Chatbot Support System.</p>
    </footer>

</body>
</html>