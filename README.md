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
## Additional Information 
- data yang digunakan adalah data dummy dari <a href="https://archive.ics.uci.edu/dataset/275/bike+sharing+dataset">UCI Machine Learning</a>
