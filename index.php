<?php
// index.php - Sample user training page
session_start();
$_SESSION['username'] = 'example_user1';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>صفحه تمرین تایپ</title>
</head>
<body>
    <h1>تمرین تایپ آنلاین</h1>
    <form>
        <div>
            <label for="username">نام کاربر:</label>
            <input type="text" id="username" name="username" placeholder="نام خود را وارد کنید">
        </div>
        <br>
        <div>
            <label for="practice_text">متن تمرینی:</label>
            <textarea id="practice_text" name="practice_text" rows="5" placeholder="متن فوق را تایپ کنید"></textarea>
        </div>
    </form>

    <?php include 'tracker.php'; ?>
</body>
</html>
