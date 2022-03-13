<?php
set_time_limit(0);
error_reporting(-1);
require "vendor/autoload.php";

use Maniaplanet\DedicatedServer\Xmlrpc\FaultException;
use Maniaplanet\DedicatedServer\Xmlrpc\MessageException;
use WebSocket\Client;
use Maniaplanet\DedicatedServer\Xmlrpc\GbxRemote;

class XmlRpcReceiver
{
	public $client;
	public $xmlRpcIp;
	public $xmlRpcPort;
	public $ws;
	public $authLogin;
	public $authPassword;

	function __construct($config)
	{
		$this->xmlRpcIp = $config["xmlRpcIp"];
		$this->xmlRpcPort = $config["xmlRpcPort"];
		$this->authLogin = $config["authLogin"];
		$this->authPassword = $config["authPassword"];

		$this->client = $this->initClient();
		$this->configureClient();
        $this->removeAllMaps();

        try {
            $this->client->query("ChatSendServerMessage", ["Match ended. Kicking all players."]);
            $this->kickAllPlayers();
        } catch (FaultException $exception) {
            var_dump("Could not kick players in constructor.");
            var_dump($exception->getMessage());
            die(1); //Means it's change in progress and we should restart server until we are able to reset score.
        } catch (MessageException $exception) {
            var_dump("Could not kick players in constructor.");
            var_dump($exception->getMessage());
            die(1); //Means it's change in progress and we should restart server until we are able to reset score.
        }

        try {
            $this->client->query("RestartMap", []);
        } catch (FaultException $exception) {
            var_dump("Could not restart map in constructor.");
            var_dump($exception->getMessage());
            die(1); //Means it's change in progress and we should restart server until we are able to reset score.
        } catch (MessageException $exception) {
            var_dump("Could not restart map in constructor.");
            var_dump($exception->getMessage());
            die(1); //Means it's change in progress and we should restart server until we are able to reset score.
        }

        try {
            $this->ws = new Client($config["webSocketUrl"]);
            $this->ws->send(json_encode([
                "type" => "ACK"
            ]));
        } catch (\Throwable $exception) {
            echo 'connection to ws failed';
            exit(1);
        }

		$this->loop();
	}

	public function initClient()
	{
		try {
			$client = new GbxRemote(
				$this->xmlRpcIp, $this->xmlRpcPort
			);
		} catch (\Throwable $ex) {
			die("An error occurred. Could not init client.");
		}

		echo "ESAC: Successfully connected to $this->xmlRpcPort\n";
		return $client;
	}

	public function configureClient()
	{
		$this->client->query("Authenticate", array($this->authLogin, $this->authPassword));
		$this->client->query("EnableCallbacks", array(true));
		$this->client->query("SetCallVoteTimeOut", array(0));
		$this->client->query("SetApiVersion", array("2013-04-16"));
		$this->client->query('TriggerModeScriptEventArray', array("XmlRpc.EnableCallbacks", array("true")));
	}

	private function removeAllMaps()
    {
        $maps = $this->client->query("GetMapList", [100, 0]);
        var_dump(["removeAllMaps" => $maps]);

        foreach ($maps as $map) {
            try {
                $this->client->query("RemoveMap", [$map["FileName"]]);
            } catch (FaultException $exception) {
                var_dump("exception thrown");
                var_dump($exception->getMessage());
            } catch (MessageException $exception) {
                var_dump("exception thrown");
                var_dump($exception->getMessage());
            }
        }

        //Waiting Map
        $this->client->query("AddMap", ["Vergasse.Map.Gbx"]);
    }

	public function loop()
	{
		flush();
		while (true) {
		    try {
                $calls = $this->client->getCallbacks();
            } catch (\Throwable $exception) {
		        var_dump($exception->getMessage());
            }

            $res   = $this->ws->receive();

			$this->handleCalls($calls);
			$this->handleWSResponse($res);
			flush();
		}
	}

	public function handleCalls($calls)
	{
		foreach ($calls as $call) {
			echo $call[0] . "\n";
			$this->ws->send(json_encode([
				"call" => $call
			]));
		}
		flush();
	}

	public function handleWSResponse($res)
    {
        if (!$res) {
            flush();
            return;
        }

        $res = json_decode($res, true);

        if (isset($res['ping'])) {
            $this->ws->send(json_encode([
                "pong" => "pong"
            ]));
            return;
        }

        switch($res['type']) {
            case 'restart': exit(1); break;
            case 'query': {
                try {
                    $this->client->query($res['data']['query'], $res['data']['params']);
                } catch (FaultException $exception) {
                    var_dump("Could not execute query sent from WS.");
                    var_dump($exception->getMessage());
                    var_dump($res);
                } catch (MessageException $exception) {
                    var_dump("Could not execute query sent from WS.");
                    var_dump($exception->getMessage());
                    var_dump($res);
                }
            } break;
            case 'endMatchIfServerEmpty': {
                $players = $this->getPlayers();
                if (count($players) == 0) {
                    var_dump("Ending match due to missing players.");
                    $this->ws->send(json_encode([
                        "call" => ["noPlayers", []]
                    ]));
                    die(1);
                }
                break;
            }
        }

        flush();
    }

    public function kickAllPlayers()
    {
        $players = $this->getPlayers();

        if (count($players)==0) return;

        foreach ($players as $player) {
            $i = $player['PlayerId'];

            //PlayerId 0 = Server
            //Why the fuck is the server returned as part of the list of players?!
            if ($i == 0) {
                continue;
            }

            try {
                $this->client->query("KickId", [$i, "Server restart."]);
            } catch (FaultException $exception) {
                var_dump("Could not kick player.");
                var_dump($exception->getMessage());
            } catch (MessageException $exception) {
                var_dump("Could not kick player.");
                var_dump($exception->getMessage());
            }
        }
    }

    public function getPlayers()
    {
        try {
            $players = $this->client->query("GetPlayerList", [64, 0]);
        } catch (FaultException $exception) {
            var_dump("Could not get player list.");
            var_dump($exception->getMessage());
        } catch (MessageException $exception) {
            var_dump("Could not get player list.");
            var_dump($exception->getMessage());
        }
        array_shift($players);
        return $players;
    }
}
