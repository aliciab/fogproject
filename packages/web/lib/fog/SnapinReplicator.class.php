<?php
class SnapinReplicator extends FOGBase
{
	var $dev = SNAPINREPDEVICEOUTPUT;
	var $log = SNAPINREPLOGPATH;
	var $zzz = SNAPINREPSLEEPTIME;
	public function outall($string)
	{
		$this->FOGCore->out($string,$this->dev);
		$this->FOGCore->wlog($string,$this->log);
	}
	public function serviceStart()
	{
		$this->FOGCore->out($this->FOGCore->getBanner(),$this->dev);
		$this->outall(" * Starting FOG Snapin Replicator Service");
		sleep(5);
		$this->outall(sprintf(" * Checking for new tasks every %s seconds.",$this->zzz));
		$this->outall(sprintf(" * Starting service loop."));
	}
	private function commonOutput()
	{
		$StorageNode = current($this->getClass('StorageNodeManager')->find(array('isMaster' => 1,'isEnabled' => 1, 'ip' => $this->FOGCore->getIPAddress())));
		try
		{
			if ($StorageNode)
			{
				$this->FOGCore->out(' * I am the group manager.',$this->dev);
				$this->FOGCore->wlog(' * I am the group manager.','/opt/fog/log/groupmanager.log');
				$this->outall(" * Starting Snapin Replication.");
				$this->outall(sprintf(" * We are group ID: #%s",$StorageNode->get('storageGroupID')));
				$this->outall(sprintf(" * We have node ID: #%s",$StorageNode->get('id')));
				$StorageNodes = $this->getClass('StorageNodeManager')->find(array('storageGroupID' => $StorageNode->get('storageGroupID')));
				foreach($StorageNodes AS $OtherNode)
				{
					if ($OtherNode->get('id') != $StorageNode->get('id') && $OtherNode->get('isEnabled'))
						$StorageNodeCount[] = $OtherNode;
				}
				if (count($StorageNodeCount) > 0)
				{
					$this->outall(sprintf(" * Found: %s other member(s).",count($StorageNodeCount)));
					$this->outall(sprintf(''));
					$myRoot = rtrim($StorageNode->get('snapinpath'),'/');
					$this->outall(sprintf(" * My root: %s",$myRoot));
					$this->outall(sprintf(" * Starting Sync."));
					foreach($StorageNodeCount AS $StorageNodeFTP)
					{
						if ($StorageNodeFTP->get('isEnabled'))
						{
							$username = $StorageNodeFTP->get('user');
							$password = $StorageNodeFTP->get('pass');
							$ip = $StorageNodeFTP->get('ip');
							$remRoot = rtrim($StorageNodeFTP->get('snapinpath'),'/');
							$this->outall(sprintf(" * Syncing: %s",$StorageNodeFTP->get('name')));
							$process = popen("lftp -e \"set ftp:list-options -a;set net:max-retries 1;set net:timeout 30; mirror -R -vvv --exclude 'dev/' --delete $myRoot $remRoot; exit\" -u $username,$password $ip 2>&1","r");
							while(!feof($process) && $process != null)
							{
								$output = fgets($process,256);
								$this->outall(sprintf(" * SubProcess -> %s",$output));
							}
							pclose($process);
							$this->outall(sprintf(" * SubProcess -> Complete"));
						}
					}
				}
				else
					$this->outall(sprintf(" * I am the only member, no need to copy anything!."));
			}
		}
		catch (Exception $e)
		{
			$this->outall(' * '.$e->getMessage());
		}
	}
	public function serviceRun()
	{
		$this->FOGCore->out(' ',$this->dev);
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
		$this->commonOutput();
		$this->FOGCore->out(' +---------------------------------------------------------',$this->dev);
	}
}