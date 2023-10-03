<?php
// phpqsession.php -- HotCRP session handler wrapping PHP sessions
// Copyright (c) 2006-2022 Eddie Kohler; see LICENSE.

class PHPQsession extends Qsession {
    function start($sid) {
        if ($sid !== null) {
            session_id($sid);
        }
        session_start();
        return session_id();
    }

    function new_sid() {
        return session_create_id();
    }

    function commit() {
        if ($this->sopen) {
            session_commit();
            $this->sopen = false;
        }
    }

    function all() {
        return $_SESSION;
    }

    function clear() {
        assert($this->sopen);
        $_SESSION = [];
    }

    function has($key) {
        return $this->sopen && isset($_SESSION[$key]);
    }

    function get($key) {
        return $this->sopen ? $_SESSION[$key] ?? null : null;
    }

    function set($key, $value) {
        assert($this->sopen);
        $_SESSION[$key] = $value;
    }

    function unset($key) {
        assert($this->sopen);
        unset($_SESSION[$key]);
    }

    function has2($key1, $key2) {
        return $this->sopen && isset($_SESSION[$key1][$key2]);
    }

    function get2($key1, $key2) {
        return $this->sopen ? $_SESSION[$key1][$key2] ?? null : null;
    }

    function set2($key1, $key2, $value) {
        assert($this->sopen);
        $_SESSION[$key1][$key2] = $value;
    }

    function unset2($key1, $key2) {
        assert($this->sopen);
        if (isset($_SESSION[$key1])) {
            unset($_SESSION[$key1][$key2]);
            if (empty($_SESSION[$key1])) {
                unset($_SESSION[$key1]);
            }
        }
    }
}
