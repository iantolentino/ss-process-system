<?php
// POST only (csrf already checked by the router) so a stray link cannot sign anyone out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') logout();
redirect('login');
