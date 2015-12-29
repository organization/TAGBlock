<?php

namespace ifteam\TAGBlock\task;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;

class TAGBlockAsyncSearchTask extends AsyncTask {
	public $players;
	public $tagBlock;
	public $showed;
	public $needToAdd = null;
	public $needToRemove = null;
	public function __construct($players, $tagBlock, $showed) {
		$this->players = $players;
		$this->tagBlock = $tagBlock;
		$this->showed = $showed;
	}
	public function onRun() {
		$needToAdd = array ();
		$needToRemove = array ();
		
		foreach ( $this->players as $player ) {
			if (! isset ( $this->tagBlock [$player ["level"]] ))
				continue;
			foreach ( $this->tagBlock [$player ["level"]] as $tagPos => $message ) {
				$explodePos = explode ( ".", $tagPos );
				if (! isset ( $explodePos [2] ))
					continue;
				$dx = abs ( $explodePos [0] - $player ["x"] );
				$dy = abs ( $explodePos [1] - $player ["y"] );
				$dz = abs ( $explodePos [2] - $player ["z"] );
				
				if (! ($dx <= 25 and $dy <= 25 and $dz <= 25)) {
					if (isset ( $this->showed [$player ["name"]] [$tagPos] ))
						$needToRemove [$player ["name"]][] = $tagPos;
				} else {
					$needToAdd [$player ["name"]][] = $tagPos;
				}
			}
		}
		
		$this->needToAdd = json_encode ( $needToAdd );
		$this->needToRemove = json_encode ( $needToRemove );
	}
	public function onCompletion(Server $server) {
		if ($this->needToAdd === null and $this->needToRemove === null)
			return;
		
		$needToAdd = ( array ) json_decode ( $this->needToAdd, true );
		$needToRemove = ( array ) json_decode ( $this->needToRemove, true );
		
		$players = array ();
		foreach ( $this->players as $player )
			$players [] = $player ["name"];
		
		$tagBlock = $server->getPluginManager ()->getPlugin ( "TAGBlock" );
		$tagBlock->receiveSearchProcess ( $players, $needToAdd, $needToRemove );
	}
}

?>