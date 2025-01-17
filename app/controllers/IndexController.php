<?php
use Fox\CSRF;
use Fox\Paginator;
use Fox\Request;
use Fox\Cookies;
use Illuminate\Database\Capsule\Manager as DB;

class IndexController extends Controller {

    public function index($rev = null, $page = 1) {

        $revisions = Revisions::where('visible', 1)->get();

        if ($rev) {
            $revision = Revisions::where('revision', $rev)->first();

            if (!$revision) {
                $this->setView("errors/show404");
                return false;
            }

            $servers = Servers::getByRevision($revision, $page);
            $this->set("page_title", "{$revision->revision} Servers");
            $this->set("meta_info", "{$revision->revision} Project Zanaris Community Servers.");
            $this->set("revision", $revision);
        } else {
            $servers = Servers::getAll($page);
        }

$data = [
    'users' => [
        'total' => Users::count(),
    ],
    'servers' => [
        'total' => Servers::count(),
    ],
    'votes' => [
        'total' => Votes::count(),
    ],
];


        $this->set("data", $data);
        $this->set("servers", $servers);
        $this->set("revisions", $revisions);
        $this->set("server_count", $servers->total());
        return true;
    }

    public function beta($rev = null, $page = 1) {
        return $this->index($rev, $page);
    }

    public function staffpanel($rev = null, $page = 1) {
        $revisions = Revisions::where('visible', 1)->get();

        if ($rev) {
            $revision = Revisions::where('revision', $rev)->first();

            if (!$revision) {
                $this->setView("errors/show404");
                return false;
            }

            $servers = Servers::getByRevision($revision, $page);
            $this->set("page_title", "{$revision->revision} Servers");
            $this->set("meta_info", "{$revision->revision} Project Zanaris Community Servers.");
            $this->set("revision", $revision);
        } else {
            $servers = Servers::getStaffPanel($page);
        }

        $data = ['servers' => ['total' => Servers::count()]];

        $this->set("data", $data);
        $this->set("servers", $servers);
        $this->set("revisions", $revisions);
        $this->set("server_count", $servers->total());
        return true;
    }

    public function details($id) {
        $server = Servers::getServer($id);

        if (!$server) {
            $this->setView("errors/show404");
            return false;
        }

        $seo = Functions::friendlyTitle($server->id . '-' . $server->title);

        $this->set("server", $server);
        $this->set("purifier", $this->getPurifier());
        $this->set("page_title", $server->title);

        if ($server->meta_tags) {
            $this->set("meta_tags", implode(',', json_decode($server->meta_tags, true)));
        }

        $this->set("meta_info", $this->filter($server->meta_info));
        $this->set("seo_link", $seo);

        $body = str_replace(["<img src", "\"img-fluid\""], ["<img data-src", "\"lazy img-fluid\""], $server->description);
        $this->set("description", $body);

        return true;
    }

    public function logout() {
        $token = $this->cookies->get("access_token");

        try {
            $discord = new Discord($token);
            $discord->revokeAccess();
        } catch (Exception $e) {
            // Handle exception silently
        }

        $this->cookies->delete("access_token");
        $this->redirect("");
        exit;
    }

    public $access = [
        'login_required' => false,
        'roles' => []
    ];

    public function beforeExecute() {
        if ($this->getActionName() === "logout") {
            $this->request = Request::getInstance();
            $this->cookies = Cookies::getInstance();
            $this->disableView(false);
            return true;
        }

        return parent::beforeExecute();
    }
}
