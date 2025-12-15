# PowerShell script to push project to GitHub
# Make sure Git is installed and configured before running this script

Write-Host "Initializing Git repository..." -ForegroundColor Green
git init

Write-Host "Adding remote repository..." -ForegroundColor Green
git remote add origin https://github.com/cpsqc04/cpsqc.git

Write-Host "Adding all files..." -ForegroundColor Green
git add .

Write-Host "Creating initial commit..." -ForegroundColor Green
git commit -m "Initial commit"

Write-Host "Pushing to GitHub..." -ForegroundColor Green
git branch -M main
git push -u origin main

Write-Host "Done! Project pushed to GitHub." -ForegroundColor Green

