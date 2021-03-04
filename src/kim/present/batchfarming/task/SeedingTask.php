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
 * @noinspection PhpInternalEntityUsedInspection
 * @noinspection PhpDeprecationInspection
 */

declare(strict_types=1);

namespace kim\present\batchfarming\task;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\sound\BlockPlaceSound;

use function spl_object_hash;

final class SeedingTask extends Task{
    /**
     * Counts of tasks launched by the each player
     * It is used to disable continuous use when a function is already in use.
     *
     * @var int[] string $playerHash => int $count
     */
    private static array $counts = [];

    public static function getCount(Player $player) : int{
        return self::$counts[spl_object_hash($player)] ?? 0;
    }

    private Player $owningPlayer;
    private Location $location;
    private Block $block;
    private int $targetY;

    /** @var Player[] */
    private array $hasSpawned = [];
    private int $entityRuntimeId;
    private bool $giveItemOnCancel;
    private float $motionY = 0.0;

    public function __construct(Player $owningPlayer, Location $location, Block $block, int $targetY){
        $this->owningPlayer = $owningPlayer;
        $this->location = $location;
        $this->block = $block;
        $this->targetY = $targetY;

        $this->entityRuntimeId = Entity::nextRuntimeId();
        $this->giveItemOnCancel = $owningPlayer->hasFiniteResources();

        if(!isset(self::$counts[$hash = spl_object_hash($owningPlayer)])){
            self::$counts[$hash] = 0;
        }
        self::$counts[$hash]++;

        $this->broadcastEntitySpawn();
    }

    public function onRun() : void{
        if($this->owningPlayer->isClosed() || !$this->owningPlayer->isConnected()){
            $this->getHandler()->cancel();
            return;
        }

        $world = $this->location->getWorld();
        if($world->getBlock($this->location)->isSolid()){
            if($this->placeBlock($this->location)){
                $this->giveItemOnCancel = false;
            }
            $this->getHandler()->cancel();
            return;
        }
        if($this->motionY > -0.9){
            $this->motionY -= 0.04;
        }
        $this->location->y += $this->motionY;
        $this->broadcastEntityMove();
    }

    public function onCancel() : void{
        self::$counts[$hash = spl_object_hash($this->owningPlayer)]--;
        if(self::$counts[$hash] <= 0){
            unset(self::$counts[$hash]);
        }

        $this->broadcastEntityDespawn();
        if($this->giveItemOnCancel){
            $item = $this->block->getPickedItem();
            if(
                $this->owningPlayer->isClosed() ||
                !$this->owningPlayer->isConnected() ||
                !empty($this->owningPlayer->getInventory()->addItem($item))
            ){
                $this->location->getWorld()->dropItem($this->location->add(0, 0.5, 0), $item);
            }
        }
    }

    private function broadcastEntitySpawn() : void{
        $pk = new AddActorPacket();
        $pk->entityRuntimeId = $this->entityRuntimeId;
        $pk->position = $this->location->asVector3();
        $pk->yaw = $pk->headYaw = $this->location->yaw;
        $pk->pitch = $this->location->pitch;
        $pk->type = EntityIds::FALLING_BLOCK;
        $pk->metadata = [
            EntityMetadataProperties::VARIANT => new IntMetadataProperty(RuntimeBlockMapping::getInstance()->toRuntimeId($this->block->getFullId())),
        ];

        $chunkX = $this->location->getFloorX() >> 4;
        $chunkZ = $this->location->getFloorZ() >> 4;
        foreach($this->location->getWorld()->getChunkPlayers($chunkX, $chunkZ) as $player){
            if(!$player->hasReceivedChunk($chunkX, $chunkZ))
                continue;

            $this->hasSpawned[spl_object_hash($player)] = $player;
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    private function broadcastEntityDespawn() : void{
        $pk = RemoveActorPacket::create($this->entityRuntimeId);

        foreach($this->hasSpawned as $player){
            if(!$player->isConnected())
                continue;

            unset($this->hasSpawned[spl_object_hash($player)]);
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }

    private function broadcastEntityMove() : void{
        Server::getInstance()->broadcastPackets($this->hasSpawned, [
            MoveActorAbsolutePacket::create(
                $this->entityRuntimeId,
                $this->location->add(0, 0.5, 0),
                $this->location->pitch,
                $this->location->yaw,
                $this->location->yaw
            )
        ]);
    }

    /** @return bool Whether block placement is successful */
    protected function placeBlock(Location $pos) : bool{
        $world = $pos->getWorld();
        $clicked = $pos->floor();
        $replace = $clicked->add(0, 1, 0);
        $blockReplace = $world->getBlock($replace);
        if(
            !$world->isInWorld($replace->x, $replace->y, $replace->z) ||
            !$world->isChunkLoaded($chunkX = $replace->getFloorX() >> 4, $chunkZ = $replace->getFloorZ() >> 4) ||
            !$world->isChunkGenerated($chunkX, $chunkZ) ||
            $world->isChunkLocked($chunkX, $chunkZ) ||
            !$this->block->canBePlacedAt($blockReplace, new Vector3(0.5, 0, 0.5), Facing::UP, false)
        ){
            return false;
        }

        $ev = new BlockPlaceEvent($this->owningPlayer, $this->block, $blockReplace, $world->getBlock($clicked), $this->block->getPickedItem());
        $ev->call();
        if($ev->isCancelled())
            return false;

        $world->setBlockAt($replace->x, $replace->y, $replace->z, $this->block);
        $world->addSound($this->location, new BlockPlaceSound($this->block));
        return true;
    }
}