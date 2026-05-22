param(
    [string]$Host = "127.0.0.1",
    [int]$Port = 8000
)

$ErrorActionPreference = "Stop"

php `
    -d upload_max_filesize=0 `
    -d post_max_size=0 `
    -d max_file_uploads=200 `
    -d max_input_time=-1 `
    -d max_execution_time=0 `
    artisan serve --host=$Host --port=$Port
