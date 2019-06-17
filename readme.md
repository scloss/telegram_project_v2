Telegram BOT API Wrapper:

This repository is a wrapper for telegram bot. It uses redis and mysql as chaching server. It is created using the getUpdates method of telegram bot api. Please follow the below steps to use this.

1. Change variables value in .env file. BOT_NAME and BOT_TOKEN is your telegram bot's name and token shared by telegram

2. REDIS_INBOX_CAPACITY holds the minimum amount of message redis will hold. when redis reaches this threshold value the data will be moved to mysql database.

3. run php artisan migrate to migrate mysql database.

4. run the chatup api(in route/api.php) under a cronjob. It will continue replying to the messages. 