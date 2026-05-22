@echo off
php -d upload_max_filesize=0 -d post_max_size=0 -d max_file_uploads=200 -d max_input_time=-1 -d max_execution_time=0 artisan serve --host=127.0.0.1 --port=8000
