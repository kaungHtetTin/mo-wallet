<?php

return ['check' => fn () => !empty($_SESSION['user_id']), 'can' => null, 'login' => 'login'];
