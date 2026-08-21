<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Hỗ Trợ Tự Động - HaUI Chatbot</title>
    <style>
        :root {
            --primary: #1877f2;
            --dark: #0f172a;
            --gray: #475569;
            --light-bg: #f8fafc;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body { color: var(--dark); background: #ffffff; line-height: 1.6; }
        
        header { display: flex; justify-content: space-between; align-items: center; padding: 1.2rem 8%; border-bottom: 1px solid var(--border); position: sticky; top: 0; background: #fff; z-index: 100; }
        .logo { font-size: 1.25rem; font-weight: 700; color: var(--primary); text-decoration: none; display: flex; align-items: center; gap: 8px; }
        nav a { margin-left: 20px; text-decoration: none; color: var(--gray); font-weight: 500; font-size: 0.95rem; }
        nav a:hover { color: var(--primary); }
        .btn-nav { background: var(--primary); color: #fff !important; padding: 8px 16px; border-radius: 6px; }

        .hero { text-align: center; padding: 4.5rem 8% 3.5rem; background: linear-gradient(180deg, #f0f7ff 0%, #ffffff 100%); }
        .hero h1 { font-size: 2.2rem; margin-bottom: 1rem; color: var(--dark); }
        .hero p { font-size: 1.1rem; color: var(--gray); max-width: 650px; margin: 0 auto 2rem; }
        .btn { display: inline-block; padding: 12px 24px; font-size: 1rem; font-weight: 600; border-radius: 8px; text-decoration: none; transition: 0.2s ease; }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: #1464cc; }

        .container { max-width: 900px; margin: 0 auto; padding: 3rem 5%; }
        .section-title { text-align: center; font-size: 1.6rem; margin-bottom: 2rem; }
        
        .feature-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .feature-item { padding: 1.5rem; border: 1px solid var(--border); border-radius: 8px; background: var(--light-bg); }
        .feature-item h3 { font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--dark); }
        .feature-item p { font-size: 0.9rem; color: var(--gray); }

        footer { background: var(--dark); color: #94a3b8; padding: 2.5rem 8% 1.5rem; font-size: 0.9rem; margin-top: 3rem; }
        .footer-content { display: flex; justify-content: space-between; align-items: center; max-width: 900px; margin: 0 auto 1.5rem; flex-wrap: wrap; gap: 15px; }
        .footer-links a { color: #cbd5e1; text-decoration: none; margin-left: 15px; }
        .footer-links a:hover { color: #fff; }
        .footer-bottom { text-align: center; border-top: 1px solid #334155; padding-top: 1.2rem; font-size: 0.85rem; }
    </style>
</head>
<body>

    <header>
        <a href="/" class="logo">🤖 HaUI Chatbot System</a>
        <nav>
            <a href="/privacy-policy">Chính sách bảo mật</a>
            <a href="https://web.facebook.com/HauiChatBot.Fanpage/" target="_blank" class="btn-nav">Nhắn tin Fanpage</a>
        </nav>
    </header>

    <section class="hero">
        <h1>Hệ Thống Trả Lời & Hỗ Trợ Tự Động Fanpage</h1>
        <p>Ứng dụng nội bộ tiếp nhận yêu cầu, phản hồi thông tin và giải đáp thắc mắc của người dùng tự động 24/7 qua Facebook Messenger.</p>
        <a href="https://web.facebook.com/HauiChatBot.Fanpage/" target="_blank" class="btn btn-primary">Gửi tin nhắn thử nghiệm</a>
    </section>

    <div class="container">
        <h2 class="section-title">Chức Năng Chính Của Hệ Thống</h2>
        <div class="feature-list">
            <div class="feature-item">
                <h3>💬 Phản hồi tự động tức thì</h3>
                <p>Tự động tiếp nhận câu hỏi của người dùng và trả lời các thắc mắc thông dụng ngay khi nhận được tin nhắn.</p>
            </div>
            <div class="feature-item">
                <h3>🧭 Định tuyến thông tin</h3>
                <p>Phân loại nội dung tin nhắn và chuyển tiếp dữ liệu đến hệ thống xử lý nội bộ nhằm giải quyết khiếu nại chính xác.</p>
            </div>
            <div class="feature-item">
                <h3>🔒 Bảo mật trao đổi dữ liệu</h3>
                <p>Ứng dụng sử dụng chuẩn Webhook và Facebook Graph API chính thức để đảm bảo an toàn dữ liệu người dùng.</p>
            </div>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div>
                <strong>HaUI Chatbot Support System</strong>
                <p>Dành riêng cho Fanpage: <a href="https://web.facebook.com/HauiChatBot.Fanpage/" style="color:#60a5fa;" target="_blank">fb.com/HauiChatBot.Fanpage</a></p>
            </div>
            <div class="footer-links">
                <a href="/">Trang chủ</a>
                <a href="/privacy-policy">Chính sách bảo mật</a>
                <a href="/privacy-policy#data-deletion">Xóa dữ liệu</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date("Y"); ?> HaUI Chatbot. Tất cả quyền được bảo lưu.</p>
        </div>
    </footer>

</body>
</html>