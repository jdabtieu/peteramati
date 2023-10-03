<?php
// qrequest.php -- HotCRP helper class for request objects (no warnings)
// Copyright (c) 2006-2022 Eddie Kohler; see LICENSE.

class Qrequest implements ArrayAccess, IteratorAggregate, Countable, JsonSerializable {
    /** @var ?Conf */
    private $_conf;
    /** @var ?Contact */
    private $_user;
    /** @var ?NavigationState */
    private $_navigation;
    /** @var ?string */
    private $_page;
    /** @var ?string */
    private $_path;
    /** @var string */
    private $_method;
    /** @var array<string,string> */
    private $_v;
    /** @var array<string,list> */
    private $_a = [];
    /** @var array<string,QrequestFile> */
    private $_files = [];
    private $_annexes = [];
    /** @var bool */
    private $_post_ok = false;
    /** @var bool */
    private $_post_empty = false;
    /** @var ?string */
    private $_referrer;
    /** @var null|false|SessionList */
    private $_active_list = false;
    /** @var Qsession */
    private $_qsession;

    /** @var Qrequest */
    static public $main_request;

    const ARRAY_MARKER = "__array__";

    /** @param string $method
     * @param array<string,string> $data */
    function __construct($method, $data = []) {
        $this->_method = $method;
        $this->_v = $data;
        $this->_qsession = new Qsession;
    }

    /** @param NavigationState $nav
     * @return $this */
    function set_navigation($nav) {
        $this->_navigation = $nav;
        $this->_page = $nav->page;
        $this->_path = $nav->path;
        return $this;
    }

    /** @param string $page
     * @param ?string $path
     * @return $this */
    function set_page($page, $path = null) {
        $this->_page = $page;
        $this->_path = $path;
        return $this;
    }

    /** @param ?string $referrer
     * @return $this */
    function set_referrer($referrer) {
        $this->_referrer = $referrer;
        return $this;
    }

    /** @param Conf $conf
     * @return $this */
    function set_conf($conf) {
        assert(!$this->_conf || $this->_conf === $conf);
        $this->_conf = $conf;
        return $this;
    }

    /** @param ?Contact $user
     * @return $this */
    function set_user($user) {
        assert(!$user || !$this->_conf || $this->_conf === $user->conf);
        if ($user) {
            $this->_conf = $user->conf;
        }
        $this->_user = $user;
        return $this;
    }

    /** @return $this */
    function set_qsession(Qsession $qsession) {
        $this->_qsession = $qsession;
        return $this;
    }

    /** @return string */
    function method() {
        return $this->_method;
    }
    /** @return bool */
    function is_get() {
        return $this->_method === "GET";
    }
    /** @return bool */
    function is_post() {
        return $this->_method === "POST";
    }
    /** @return bool */
    function is_head() {
        return $this->_method === "HEAD";
    }

    /** @return Conf */
    function conf() {
        return $this->_conf;
    }
    /** @return ?Contact */
    function user() {
        return $this->_user;
    }
    /** @return NavigationState */
    function navigation() {
        return $this->_navigation;
    }
    /** @return Qsession */
    function qsession() {
        return $this->_qsession;
    }

    /** @return ?string */
    function page() {
        return $this->_page;
    }
    /** @return ?string */
    function path() {
        return $this->_path;
    }
    /** @param int $n
     * @return ?string */
    function path_component($n, $decoded = false) {
        if ((string) $this->_path !== "") {
            $p = explode("/", substr($this->_path, 1));
            if ($n + 1 < count($p)
                || ($n + 1 === count($p) && $p[$n] !== "")) {
                return $decoded ? urldecode($p[$n]) : $p[$n];
            }
        }
        return null;
    }
    /** @param int $n
     * @return ?string */
    function shift_path_components($n) {
        $pos = 0;
        $path = $this->_path ?? "";
        while ($n > 0 && $pos < strlen($path)) {
            if (($pos = strpos($path, "/", $pos + 1)) === false) {
                $pos = strlen($path);
            }
            --$n;
        }
        if ($n === 0) {
            $prefix = substr($path, 0, $pos);
            $this->_path = substr($path, $pos);
            return $prefix;
        } else {
            return null;
        }
    }

    /** @return ?string */
    function referrer() {
        return $this->_referrer;
    }

    #[\ReturnTypeWillChange]
    function offsetExists($offset) {
        return array_key_exists($offset, $this->_v);
    }
    #[\ReturnTypeWillChange]
    function offsetGet($offset) {
        return $this->_v[$offset] ?? null;
    }
    #[\ReturnTypeWillChange]
    function offsetSet($offset, $value) {
        if (is_array($value)) {
            error_log("array offsetSet at " . debug_string_backtrace());
        }
        $this->_v[$offset] = $value;
        unset($this->_a[$offset]);
    }
    #[\ReturnTypeWillChange]
    function offsetUnset($offset) {
        unset($this->_v[$offset]);
        unset($this->_a[$offset]);
    }
    #[\ReturnTypeWillChange]
    /** @return Iterator<string,mixed> */
    function getIterator() {
        return new ArrayIterator($this->as_array());
    }
    /** @param string $name
     * @param int|float|string $value
     * @return void */
    function __set($name, $value) {
        if (is_array($value)) {
            error_log("array __set at " . debug_string_backtrace());
        }
        $this->_v[$name] = $value;
        unset($this->_a[$name]);
    }
    /** @param string $name
     * @return ?string */
    function __get($name) {
        return $this->_v[$name] ?? null;
    }
    /** @param string $name
     * @return bool */
    function __isset($name) {
        return isset($this->_v[$name]);
    }
    /** @param string $name */
    function __unset($name) {
        unset($this->_v[$name]);
        unset($this->_a[$name]);
    }
    /** @param string $name
     * @return bool */
    function has($name) {
        return array_key_exists($name, $this->_v);
    }
    /** @param string $name
     * @return ?string */
    function get($name) {
        return $this->_v[$name] ?? null;
    }
    /** @param string $name
     * @param string $value */
    function set($name, $value) {
        $this->_v[$name] = $value;
        unset($this->_a[$name]);
    }
    /** @param string $name
     * @return bool */
    function has_a($name) {
        return isset($this->_a[$name]);
    }
    /** @param string $name
     * @return ?list */
    function get_a($name) {
        return $this->_a[$name] ?? null;
    }
    /** @param string $name
     * @param list $value */
    function set_a($name, $value) {
        $this->_v[$name] = self::ARRAY_MARKER;
        $this->_a[$name] = $value;
    }
    /** @return $this */
    function set_req($name, $value) {
        if (is_array($value)) {
            $this->_v[$name] = self::ARRAY_MARKER;
            $this->_a[$name] = $value;
        } else {
            $this->_v[$name] = $value;
            unset($this->_a[$name]);
        }
        return $this;
    }
    #[\ReturnTypeWillChange]
    /** @return int */
    function count() {
        return count($this->_v);
    }
    #[\ReturnTypeWillChange]
    function jsonSerialize() {
        return $this->as_array();
    }
    /** @return array<string,mixed> */
    function as_array() {
        return $this->_v;
    }
    /** @param string ...$keys
     * @return array<string,mixed> */
    function subset_as_array(...$keys) {
        $d = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $this->_v))
                $d[$k] = $this->_v[$k];
        }
        return $d;
    }
    /** @return object */
    function as_object() {
        return (object) $this->as_array();
    }
    /** @return list<string> */
    function keys() {
        return array_keys($this->_v);
    }
    /** @param string $key
     * @return bool */
    function contains($key) {
        return array_key_exists($key, $this->_v);
    }
    /** @param string $name
     * @param array|QrequestFile $finfo
     * @return $this */
    function set_file($name, $finfo) {
        if (is_array($finfo)) {
            $this->_files[$name] = new QrequestFile($finfo);
        } else {
            $this->_files[$name] = $finfo;
        }
        return $this;
    }
    /** @param string $name
     * @param string $content
     * @param ?string $filename
     * @param ?string $mimetype
     * @return $this */
    function set_file_content($name, $content, $filename = null, $mimetype = null) {
        $this->_files[$name] = new QrequestFile([
            "name" => $filename ?? "__set_file_content.$name",
            "type" => $mimetype,
            "size" => strlen($content),
            "content" => $content
        ]);
        return $this;
    }
    /** @return bool */
    function has_files() {
        return !empty($this->_files);
    }
    /** @param string $name
     * @return bool */
    function has_file($name) {
        return isset($this->_files[$name]);
    }
    /** @param string $name
     * @return ?QrequestFile */
    function file($name) {
        return $this->_files[$name] ?? null;
    }
    /** @param string $name
     * @return string|false */
    function file_filename($name) {
        $fn = false;
        if (array_key_exists($name, $this->_files)) {
            $fn = $this->_files[$name]->name;
        }
        return $fn;
    }
    /** @param string $name
     * @return int|false */
    function file_size($name) {
        $sz = false;
        if (array_key_exists($name, $this->_files)) {
            $sz = $this->_files[$name]->size;
        }
        return $sz;
    }
    /** @param string $name
     * @param int $offset
     * @param ?int $maxlen
     * @return string|false */
    function file_contents($name, $offset = 0, $maxlen = null) {
        $data = false;
        if (array_key_exists($name, $this->_files)) {
            $finfo = $this->_files[$name];
            if (isset($finfo->content)) {
                $data = substr($finfo->content, $offset, $maxlen ?? PHP_INT_MAX);
            } else if ($maxlen === null) {
                $data = @file_get_contents($finfo->tmp_name, false, null, $offset);
            } else {
                $data = @file_get_contents($finfo->tmp_name, false, null, $offset, $maxlen);
            }
        }
        return $data;
    }
    function files() {
        return $this->_files;
    }
    /** @return bool */
    function has_annexes() {
        return !empty($this->_annexes);
    }
    /** @return array<string,mixed> */
    function annexes() {
        return $this->_annexes;
    }
    /** @param string $name
     * @return bool */
    function has_annex($name) {
        return isset($this->_annexes[$name]);
    }
    /** @param string $name */
    function annex($name) {
        return $this->_annexes[$name] ?? null;
    }
    /** @template T
     * @param string $name
     * @param class-string<T> $class
     * @return T */
    function checked_annex($name, $class) {
        $x = $this->_annexes[$name] ?? null;
        if (!$x || !($x instanceof $class)) {
            throw new Exception("Bad annex $name");
        }
        return $x;
    }
    /** @param string $name */
    function set_annex($name, $x) {
        $this->_annexes[$name] = $x;
    }
    /** @return void */
    function approve_token() {
        $this->_post_ok = true;
    }
    /** @return bool */
    function valid_token() {
        return $this->_post_ok;
    }
    /** @return bool */
    function valid_post() {
        return $this->_post_ok && $this->_method === "POST";
    }
    /** @return void */
    function set_post_empty() {
        $this->_post_empty = true;
    }
    /** @return bool */
    function post_empty() {
        return $this->_post_empty;
    }

    /** @param string $e
     * @return ?bool */
    function xt_allow($e) {
        if ($e === "post") {
            return $this->_method === "POST" && $this->_post_ok;
        } else if ($e === "anypost") {
            return $this->_method === "POST";
        } else if ($e === "getpost") {
            return in_array($this->_method, ["POST", "GET", "HEAD"]) && $this->_post_ok;
        } else if (str_starts_with($e, "req.")) {
            foreach (explode(" ", $e) as $w) {
                if (str_starts_with($w, "req.")
                    && $this->has(substr($w, 4))) {
                    return true;
                }
            }
            return false;
        } else {
            return null;
        }
    }

    /** @param NavigationState $nav */
    static function make_global($nav) : Qrequest {
        global $Qreq;
        $qreq = new Qrequest($_SERVER["REQUEST_METHOD"]);
        $qreq->set_navigation($nav);
        foreach ($_GET as $k => $v) {
            $qreq->set_req($k, $v);
        }
        foreach ($_POST as $k => $v) {
            $qreq->set_req($k, $v);
        }
        if (empty($_POST)) {
            $qreq->set_post_empty();
        }
        if (isset($_SERVER["HTTP_REFERER"])) {
            $qreq->set_referrer($_SERVER["HTTP_REFERER"]);
        }

        // $_FILES requires special processing since we want error messages.
        $errors = [];
        $too_big = false;
        foreach ($_FILES as $nx => $fix) {
            if (is_array($fix["error"])) {
                $fis = [];
                foreach (array_keys($fix["error"]) as $i) {
                    $fis[$i ? "$nx.$i" : $nx] = ["name" => $fix["name"][$i], "type" => $fix["type"][$i], "size" => $fix["size"][$i], "tmp_name" => $fix["tmp_name"][$i], "error" => $fix["error"][$i]];
                }
            } else {
                $fis = [$nx => $fix];
            }
            foreach ($fis as $n => $fi) {
                if ($fi["error"] == UPLOAD_ERR_OK) {
                    if (is_uploaded_file($fi["tmp_name"])) {
                        $qreq->set_file($n, $fi);
                    }
                } else if ($fi["error"] != UPLOAD_ERR_NO_FILE) {
                    if ($fi["error"] == UPLOAD_ERR_INI_SIZE
                        || $fi["error"] == UPLOAD_ERR_FORM_SIZE) {
                        $errors[] = $e = MessageItem::error("Uploaded file too large");
                        if (!$too_big) {
                            $errors[] = MessageItem::inform("The maximum upload size is " . ini_get("upload_max_filesie") . "B.");
                            $too_big = true;
                        }
                    } else if ($fi["error"] == UPLOAD_ERR_PARTIAL) {
                        $errors[] = $e = MessageItem::error("File upload interrupted");
                    } else {
                        $errors[] = $e = MessageItem::error("Error uploading file");
                    }
                    $e->landmark = $fi["name"] ?? null;
                }
            }
        }
        if (!empty($errors)) {
            $qreq->set_annex("upload_errors", $errors);
        }
        Qrequest::$main_request = $Qreq = $qreq;
        return $qreq;
    }


    /** @param string|list<string> $title
     * @param string $id
     * @param array{paperId?:int|string,body_class?:string,action_bar?:string,title_div?:string,subtitle?:string,save_messages?:bool} $extra */
    function print_header($title, $id, $extra = []) {
        if (!$this->_conf->_header_printed) {
            $this->_conf->print_head_tag($this, $title, $extra);
            $this->_conf->print_body_entry($this, $title, $id, $extra);
        }
    }

    function print_footer() {
        echo "<hr class=\"c\"></div>", // class='body'
            '<div id="footer">',
            $this->_conf->opt("extraFooter") ?? "";
        if (!$this->_conf->opt("noFooterVersion")) {
            if ($this->_user && $this->_user->privChair) {
                echo '<a class="noq" href="https://github.com/kohler/peteramati/">PA</a>',
                    " v", PA_VERSION, " [";
                if (($git_data = Conf::git_status())
                    && $git_data[0] !== $git_data[1]) {
                    echo substr($git_data[0], 0, 7), "... ";
                }
                echo round(memory_get_peak_usage() / (1 << 20)), "M]";
            } else {
                echo "<!-- Version ", PA_VERSION, " -->";
            }
        }
        echo '</div>', Ht::unstash(), "</body>\n</html>\n";
    }

    static function print_footer_hook(Contact $user, Qrequest $qreq) {
        $qreq->print_footer();
    }


    /** @return bool */
    function has_active_list() {
        return !!$this->_active_list;
    }

    /** @return ?SessionList */
    function active_list() {
        if ($this->_active_list === false) {
            $this->_active_list = null;
        }
        return $this->_active_list;
    }

    function set_active_list(SessionList $list = null) {
        assert($this->_active_list === false);
        $this->_active_list = $list;
    }


    /** @return void */
    function open_session() {
        $this->_qsession->open();
    }

    /** @return ?string */
    function qsid() {
        return $this->_qsession->sid;
    }

    /** @param string $key
     * @return bool */
    function has_gsession($key) {
        return $this->_qsession->has($key);
    }

    function clear_gsession() {
        $this->_qsession->clear();
    }

    /** @param string $key
     * @return mixed */
    function gsession($key) {
        return $this->_qsession->get($key);
    }

    /** @param string $key
     * @param mixed $value */
    function set_gsession($key, $value) {
        $this->_qsession->set($key, $value);
    }

    /** @param string $key */
    function unset_gsession($key) {
        $this->_qsession->unset($key);
    }

    /** @param string $key
     * @return bool */
    function has_csession($key) {
        return $this->_conf
            && $this->_conf->session_key !== null
            && $this->_qsession->has2($this->_conf->session_key, $key);
    }

    /** @param string $key
     * @return mixed */
    function csession($key) {
        if ($this->_conf && $this->_conf->session_key !== null) {
            return $this->_qsession->get2($this->_conf->session_key, $key);
        } else {
            return null;
        }
    }

    /** @param string $key
     * @param mixed $value */
    function set_csession($key, $value) {
        if ($this->_conf && $this->_conf->session_key !== null) {
            $this->_qsession->set2($this->_conf->session_key, $key, $value);
        }
    }

    /** @param string $key */
    function unset_csession($key) {
        if ($this->_conf && $this->_conf->session_key !== null) {
            $this->_qsession->unset2($this->_conf->session_key, $key);
        }
    }

    /** @param bool $allow_empty
     * @return string */
    function post_value($allow_empty = false) {
        $sid = $this->_qsession->sid;
        if ($sid === null && !$allow_empty) {
            $this->_qsession->open();
            $sid = $this->_qsession->sid;
        }
        if ($sid === null || $sid === "") {
            return ".empty";
        }
        return urlencode(substr($sid, strlen($sid) > 16 ? 8 : 0, 12));
    }
}

class QrequestFile {
    /** @var string */
    public $name;
    /** @var string */
    public $type;
    /** @var int */
    public $size;
    /** @var ?string */
    public $tmp_name;
    /** @var ?string */
    public $content;
    /** @var int */
    public $error;

    /** @param array{name?:string,type?:string,size?:int,tmp_name?:?string,content?:?string,error?:int} $a */
    function __construct($a) {
        $this->name = $a["name"] ?? "";
        $this->type = $a["type"] ?? "application/octet-stream";
        $this->size = $a["size"] ?? 0;
        $this->tmp_name = $a["tmp_name"] ?? null;
        $this->content = $a["content"] ?? null;
        $this->error = $a["error"] ?? 0;
    }

    /** @return array{name:string,type:string,size:int,tmp_name?:string,content?:string,error:int}
     * @deprecated */
    function as_array() {
        $a = ["name" => $this->name, "type" => $this->type, "size" => $this->size];
        if ($this->tmp_name !== null) {
            $a["tmp_name"] = $this->tmp_name;
        }
        if ($this->content !== null) {
            $a["content"] = $this->content;
        }
        $a["error"] = $this->error;
        return $a;
    }
}
