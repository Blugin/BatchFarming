<?php

/**
 *  ____                           _   _  ___
 * |  _ \ _ __ ___  ___  ___ _ __ | |_| |/ (_)_ __ ___
 * | |_) | '__/ _ \/ __|/ _ \ '_ \| __| ' /| | '_ ` _ \
 * |  __/| | |  __/\__ \  __/ | | | |_| . \| | | | | | |
 * |_|   |_|  \___||___/\___|_| |_|\__|_|\_\_|_| |_| |_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author  PresentKim (debe3721@gmail.com)
 * @link    https://github.com/PresentKim
 * @license https://www.gnu.org/licenses/lgpl-3.0 LGPL-3.0 License
 *
 *   (\ /)
 *  ( . .) ♥
 *  c(")(")
 *
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpDocSignatureInspection
 * @noinspection SpellCheckingInspection
 * @noinspection PhpDeprecationInspection
 */

declare(strict_types=1);

namespace kim\present\batchfarming\task;

use kim\present\batchfarming\object\SeedObject;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddItemActorPacket;
use pocketmine\network\mcpe\protocol\TakeItemActorPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\World;

use function lcg_value;
use function spl_object_hash;

final class CollectTask extends Task{
    private Player $owningPlayer;
    private World $world;
    private SeedObject $seed;

    /** @var Player[] */
    private array $hasSpawned = [];

    public function __construct(Player $owningPlayer, World $world, SeedObject $seed){
        $this->owningPlayer = $owningPlayer;
        $this->world = $world;
        $this->seed = $seed;

        $pk = new AddItemActorPacket();
        $pk->entityRuntimeId = $this->seed->entityRuntimeId;
        $pk->item = TypeConverter::getInstance()->coreItemStackToNet($this->seed->block->getPickedItem());
        $pk->position = $this->seed->add(0, 0.25, 0.5);
        $pk->motion = new Vector3(lcg_value() * 0.2 - 0.1, 0.2, lcg_value() * 0.2 - 0.1);
        $chunkX = $seed->getFloorX() >> 4;
        $chunkZ = $seed->getFloorZ() >> 4;
        foreach($this->world->getChunkPlayers($chunkX, $chunkZ) as $player){
            if(!$player->hasReceivedChunk($chunkX, $chunkZ))
                continue;

            $this->hasSpawned[spl_object_hash($player)] = $player;
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    public function onRun() : void{
        if($this->seed->giveItemOnCollect){
            $this->seed->collect($this->world, $this->owningPlayer);
        }

        Server::getInstance()->broadcastPackets($this->hasSpawned, [
            TakeItemActorPacket::create($this->owningPlayer->getId(), $this->seed->entityRuntimeId),
            $this->seed->getDespawnPacket()
        ]);
    }
}