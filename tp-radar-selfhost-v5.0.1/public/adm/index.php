<?php
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#0d171c"><title>TP-Radar · Admin</title><link rel="icon" href="../favicon.ico"><link rel="stylesheet" href="style.css?v=5.0.1"></head>
<body><div class="shell">
<header><div><span class="logo">🧻</span> <strong>TP Radar</strong> <span class="tag">Administration</span></div><a href="/">← Zur App</a></header>
<main>
<section id="login" class="card narrow"><h1>Admin-Anmeldung</h1><p>Nur für die Verwaltung von TP-Radar. Passwort und Authenticator-Code eingeben.</p>
<form id="loginForm"><label>Benutzername<input id="username" autocomplete="username" required value="admin"></label><label>Passwort<input id="password" type="password" autocomplete="current-password" required></label><label>Authenticator-Code (6 Ziffern)<input id="totp" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label><button type="submit">Sicher anmelden</button></form><p class="setup-link"><a href="setup.php">Ersteinrichtung des Administratorzugangs</a></p></section>
<section id="dashboard" hidden><div class="pagehead"><div><h1>Administration</h1><p>Produkte korrigieren, Preise prüfen, Geschäfte verwalten und Änderungen nachvollziehen.</p></div><button id="logout" class="secondary">Abmelden</button></div>
<nav class="tabs" aria-label="Verwaltungsbereiche"><button data-tab="products" class="active">Produkte</button><button data-tab="prices">Preise & Moderation</button><button data-tab="stores">Geschäfte</button><button data-tab="audit">Protokoll</button></nav>
<section class="card"><div class="toolbar"><label class="search">Suchen<input id="search" placeholder="Name, EAN oder ID" autocomplete="off"></label><button type="button" id="createStore" hidden>+ Geschäft hinzufügen</button><button id="reload" class="secondary">Aktualisieren</button></div><div id="tableWrap" class="table-wrap"></div></section>
</section>
</main></div>
<dialog id="editor"><form id="editForm"><div class="dialoghead"><h2 id="dialogTitle">Bearbeiten</h2><button type="button" class="secondary" id="close">Schließen</button></div><div id="editFields"></div><div class="actions"><button type="button" class="secondary" id="cancel">Abbrechen</button><button type="submit">Änderung speichern</button></div></form></dialog>
<div class="toast" id="toast" role="status" aria-live="polite" hidden></div>
<script src="admin.js?v=5.0.1" defer></script></body></html>
