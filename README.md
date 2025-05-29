# 🎬 StreamFlix - Movie Streaming Platform

**StreamFlix** is a self-hosted movie streaming platform built with PHP. It allows you to upload, edit, and stream movies directly from your own server — complete with subtitle support, dark theme UI, and HLS video playback.

---

## 🚀 Features

- 🌙 **Dark Theme** for a sleek and modern look
- 📥 **Movie Upload** with optional subtitle support
- 🧩 **Supports Full & Chunked Uploads**
- 📺 **HLS Streaming** for smooth adaptive playback
- 📝 **Edit Movies** (change details or update files)
- 🎞 **Subtitle Support** (`.vtt` files supported)
- 🔎 **Genre Filter & Search Function**
- 📊 **Admin Dashboard** to monitor and manage uploads

---

## 🛠 Tech Stack

- **PHP** – Server-side logic  
- **HTML/CSS/JS** – Frontend  
- **MySQL** – Database  
- **FFmpeg** – Video processing for HLS  
- **Apache/Nginx** – Web server

---

## 📁 Project Structure

StreamFlix/<br/>
├── assets/<br/>
│ └── css, js, images<br/>
├── uploads/<br/>
│ ├── videos/<br/>
│ └── subtitles/<br/>
├── upload.php<br/>
├── upload_handle.php<br/>
├── edit.php<br/>
├── edit_handle.php<br/>
├── watch.php<br/>
├── monitor.php<br/>
└── index.php<br/>

yaml
Copy
Edit

---

## ⚙️ Getting Started

### 1. Clone the Repository

```
git clone https://github.com/zenith-001/STREAMFLIX.git
cd STREAMFLIX
```
### 2. Set Up Database
##### Create a MySQL database (e.g. streamflix_db)

#### Import the SQL file if available (streamflix.sql)

#### Create a config.php with your DB credentials:

``` php
<?php
$host = "localhost";
$user = "root";
$pass = "";
$db = "streamflix_db";
$conn = new mysqli($host, $user, $pass, $db);
?>
```
### 3. Install FFmpeg
```bash
sudo apt install ffmpeg
```
### 4. Run the Project
```bash
php -S localhost:8000
```
#### Then open http://localhost:8000 in your browser.

## 🧪 Admin Flow
Go to upload.php to upload a movie and optional subtitle.

View all uploads on monitor.php.

Edit any entry using edit.php?id=XX.

Watch movies using watch.php?id=XX.

Upload Page	Watch Page	Monitor Dashboard

## 🙌 Credits
Developed by Zenith-001
Powered by open-source technologies.

## 📜 License
This project is licensed under the MIT License.

##### ⚠️ Note: StreamFlix is for personal or educational use. Do not use to host pirated or copyrighted content.
