# Get Started
## Testing on localhost

install xampp 1.7.2 (php 5.3.0) <a href="https://sourceforge.net/projects/xampp/files/XAMPP%20Windows/1.7.2/">disini</a>
 
- buka directory xampp/htdocs jalankan git bash
  ```
  git clone https://github.com/Aditya1614/dashboard_telkom.git
  ```
- pada xampp jalankan Apache dan MySQL
- buka phpMyAdmin 
- buat database baru dengan nama 'dashboard_telkom'
- import file 'dashboard_telkom.sql' pada database tersebut
- buka browser masuk ke
  ```
  localhost/dashboard_telkom-main
  ```
- untuk admin login dengan
  ```
  username = admin
  password = admin
  ```
## Setup SMTP for email notification
- buka xampp/sedmail/sendmail.ini
  ```
  # Example for a user configuration file

  # Set default values for all following accounts.
  defaults
  logfile "D:\tes php 5.3\xampp\sendmail\sendmail.log"
 
  # Mercury
  account Mercury
  host localhost
  from postmaster@localhost
  auth off
 
  # A freemail service example
  account Gmail
  tls on
  tls_certcheck off
  host smtp.gmail.com
  port 587
  from [emailSender]
  auth on
  user [emailSender]
  password [appPassword]

  # Set a default account
  account default : Gmail
  ```
- pastikan untuk mengubah [emailSender] sesuai dengan yang di notifications.php
- generate [appPassword] <a href="https://myaccount.google.com/apppasswords"> disini </a>
- port bisa disesuaikan  465 (SSL) or 587 (TLS)
- buka xampp/php/php.ini
- sesuaikan path, seperti contoh:
  ```
  sendmail_path = "\"D:\tes php 5.3\xampp\sendmail\sendmail.exe\" -t"
  ```

## Additional Information 
- data yang digunakan adalah data dummy dari <a href="https://archive.ics.uci.edu/dataset/275/bike+sharing+dataset">UCI Machine Learning</a>
