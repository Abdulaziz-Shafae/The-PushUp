Pushup Tracker (PHP + MySQL) — Multi-profile, one link

RULES:
- Each profile has its own history.
- Each completed day increases the NEXT target by +1.
- Next target = (total completed days) + 1
- Streak = consecutive calendar days completed up to today.
- Toggle completion via DOUBLE TAP.

FILES:
- config.php  (set DB creds)
- api.php     (backend)
- index.php   (frontend)

SETUP (InfinityFree):
1) Upload these files to /public_html/
2) Edit config.php and paste your DB credentials
3) In phpMyAdmin, run the SQL below to create tables

SQL:
CREATE TABLE IF NOT EXISTS profiles (
  device_id VARCHAR(64) NOT NULL,
  profile_id VARCHAR(32) NOT NULL,
  name VARCHAR(40) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (device_id, profile_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pushup_days (
  device_id VARCHAR(64) NOT NULL,
  profile_id VARCHAR(32) NOT NULL,
  day_date DATE NOT NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (device_id, profile_id, day_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

NOTES:
- No accounts. A random device_id is stored in the browser.
- If you clear browser storage or hit "Reset tracker", you'll get a new device_id (fresh data).
