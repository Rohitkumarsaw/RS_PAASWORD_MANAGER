<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="rgb(10, 14, 28)">
    <meta name="color-scheme" content="dark">
    <title><?php echo isset($pageTitle) ? sanitizeOutput($pageTitle) . ' | ' : ''; ?>RS PAASWORD MANAGER</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <script>(function(){var t=localStorage.getItem('pm_theme')||'dark';document.documentElement.setAttribute('data-theme',t);var a=document.querySelector('meta[name=theme-color]'),b=document.querySelector('meta[name=color-scheme]');if(a){a.content=t==='dark'?'rgb(10,14,28)':'rgb(248,250,252)'}if(b){b.content=t};var c=document.getElementById('themeToggle');if(c){var i=c.querySelector('i');if(i){i.className=t==='dark'?'fas fa-moon':'fas fa-sun'}}})();</script>
</head>
<body<?php echo isset($bodyClass) ? ' class="' . sanitizeOutput($bodyClass) . '"' : ''; ?>>
