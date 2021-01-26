<?php
class TurboController extends Controller {

    private static $disabled = false;

    public function index() {
        $packages = TurboPackages::where('visible', 1)->get();

        if ($this->user) {
            $servers = Servers::getServersByOwner($this->user->user_id);
            $this->set("servers", $servers);
        }

        $turbos = Turbos::where("expires", ">", time())->count();

        if (self::$disabled)
            $this->set("page_disabled", self::$disabled);

        $this->set("packages", $packages);
        $this->set("turbos", $turbos);

        if ($turbos == 1) {
            $nextSlot = Turbos::select("expires")
                ->where("expires", ">", time())
                ->orderBy("expires", "ASC")
                ->first();
            $this->set("nextslot", $nextSlot);
        }
        return true;
    }

    public function button() {
        $packageId = $this->request->getPost("package", "int");
        $serverId  = $this->request->getPost("server", "int");

        $turbos = Turbos::where("expires", ">", time())->count();

        if ($turbos == 1) {
            return [
                'success' => false,
                'message' => "There are currently no available slots. Please check back later."
            ];
        }

        $package  = TurboPackages::where('id', $packageId)->first();


        if (!$package) {
            return [
                'success' => false,
                'message' => "Package not found."
            ];
        }

        $server = Servers::where("owner", $this->user->user_id)
            ->where("id", $serverId)
            ->first();

        if (!$server) {
            return [
                'success' => false,
                'message' => "Server not found."
            ];
        }

        return [
            "success" => true,
            "message" => $this->getViewContents("turbo/button", [
                "package" => $package,
                "server"  => $server
            ])
        ];
    }

    public function verify() {
        $order_info = $this->request->getPost("orderDetails");
        $server_id  = $this->request->getPost("server_id", "int");

        $turbos = Turbos::where("expires", ">", time())->count();

        if ($turbos == 1) {
            return [
                'success' => false,
                'message' => "There are currently no available slots. Please check back later."
            ];
        }

        if (empty($order_info)) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "No data was received."
                ])
            ];
        }

        $buyer     = $order_info['payer'];
        $firstName = $buyer['name']['given_name'];
        $lastName  = $buyer['name']['surname'];

        $item    = $order_info['purchase_units'][0]['items'][0];
        $name    = $this->filter($item['name'], 'string');
        $sku     = $this->filter($item['sku'], 'int');
        $amount  = $this->filter($item['quantity'], 'int');
        $value   = $this->filter($item['unit_amount']['value'], 'float');

        $status  = $order_info['status'];

        if ($status != "APPROVED") {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Payment was not approved. For additional payment methods please contact us."
                ])
            ];
        }

        $package = TurboPackages::where('id', $sku)->first();

        if (!$package) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Package could not be loaded."
                ])
            ];
        }

        if ($package->price != $value) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Invalid purchase price!"
                ])
            ];
        }

        $server = Servers::where("owner", $this->user->user_id)
            ->where("id", $server_id)
            ->first();

        if (!$server) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Could not find your server!"
                ])
            ];
        }

        $turbo = Turbos::where('server_id', $server_id)
            ->where('expires', '>', time())->first();

        if ($turbo) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "This server already has Turbo. Purchase cancelled."
                ])
            ];
        }

        $token = Functions::generateString(15);
        $this->session->set("pp_key", $token);

        return [
            'success' => true,
            'message' => $this->request->getPost(),
            'token'   => $this->session->get("pp_key")
        ];
    }

    public function process() {
        $pp_key   = $this->request->getPost("pp_key", "string");
        $sess_key = $this->session->get("pp_key");

        $turbos = Turbos::where("expires", ">", time())->count();

        if ($turbos == 1) {
            return [
                'success' => false,
                'message' => "There are currently no available slots. If you were charged please contact us on discord."
            ];
        }

        if (!$pp_key || !$sess_key || $pp_key != $sess_key) {
            return [
                'success' => false,
                'message' => "Invalid Request."
            ];
        }

        $this->session->delete("pp_key");

        $order_info = $this->request->getPost("orderDetails");
        $server_id  = $this->request->getPost("server_id", "int");

        if (empty($order_info)) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "No data was received"
                ])
            ];
        }

        $buyer     = $order_info['payer'];
        $firstName = $buyer['name']['given_name'];
        $lastName  = $buyer['name']['surname'];
        $email     = $buyer['email_address'];

        $item    = $order_info['purchase_units'][0]['items'][0];
        $name    = $this->filter($item['name'], 'string');
        $sku     = $this->filter($item['sku'], 'int');
        $amount  = $this->filter($item['quantity'], 'int');

        $capture = $order_info['purchase_units'][0]['payments']['captures'][0];
        $paid    = $this->filter($capture['amount']['value'], 'float');
        $cap_id  = $order_info['id'];
        $transId = $capture['id'];
        $status  = $order_info['status'];

        if ($status != "COMPLETED") {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Payment was not complete."
                ])
            ];
        }

        $package = TurboPackages::where('id', $sku)->first();

        if (!$package) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Package could not be found"
                ])
            ];
        }

        $payment = new Payments;

        $payment->fill([
            'user_id' => $this->user->user_id,
            'username' => $this->user->username,
            'ip_address' => $this->request->getAddress(),
            'sku' => $package->id,
            'item_name' => $package->title.' Turbo',
            'email' => $email,
            'status' => $status,
            'paid' => $paid,
            'quantity' => $amount,
            'currency' => 'USD',
            'transaction_id' => $transId,
            'date_paid' => time(),
        ])->save();

        if ($package->price != $paid) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Invalid purchase price"
                ])
            ];
        }

        $expires = $package->duration;

        $server = Servers::where("owner", $this->user->user_id)
            ->where("id", $server_id)
            ->first();

        if (!$server) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Could not find your server!"
                ])
            ];
        }

        $turbo = new Turbos;

        $turbo->fill([
            'server_id' => $server_id,
            'started' => time(),
            'expires' => time() + $expires
        ]);

        if (!$turbo->save()) {
            return [
                'success' => false,
                'message' => $this->getViewContents("turbo/error", [
                    "message" => "Could not save Turbo."
                ])
            ];
        }

        return [
            'success' => true,
            'message' => $this->getViewContents("turbo/success", [
                "package" => $package,
                "server"  => $server
            ])
        ];
    }

    public function beforeExecute() {
        if ($this->getActionName() == "button" || $this->getActionName() == "process" || $this->getActionName() == "verify") {
            $this->disableView(true);
        }
        return parent::beforeExecute();
    }

}
