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
 * @noinspection PhpInternalEntityUsedInspection
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

namespace kim\present\batchfarming\entity;

use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\entity\object\FallingBlock;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\BlockPlaceSound;
use pocketmine\world\World;

/**
 * An entity that functions like an falling block.
 *
 * @see TargetingFallingEntity
 * @see FallingBlock
 */
final class TargetingFallingBlock extends TargetingFallingEntity{
    private Block $block;

    public function __construct(Location $location, Player $owningPlayer, Block $block, int $targetY, ?CompoundTag $nbt = null){
        parent::__construct($location, $owningPlayer, $targetY, $nbt);
        $this->block = $block;
    }

    /** @override for spawn to falling block */
    public static function getNetworkTypeId() : string{
        return EntityIds::FALLING_BLOCK;
    }

    /** @override for spawn to falling block */
    protected function syncNetworkData(EntityMetadataCollection $properties) : void{
        parent::syncNetworkData($properties);

        $properties->setInt(EntityMetadataProperties::VARIANT, RuntimeBlockMapping::getInstance()->toRuntimeId($this->block->getFullId()));
    }

    /** Returns picked item of block */
    public function getItem() : Item{
        return $this->block->getPickedItem();
    }

    /**
     * Place block to given vector
     * It works like World::useItemOn(), but excludes PlayerInteractEvent
     *
     * @see World::useItemOn()
     */
    protected function onRun(World $world, Block $blockClicked, Block $blockReplace) : bool{
        $replaceVector = $blockReplace->getPos();
        $clickVector = new Vector3(0.5, 0, 0.5);
        $item = $this->getItem();

        $hand = $item->getBlock(Facing::UP);
        $hand->position($world, $replaceVector->x, $replaceVector->y, $replaceVector->z);
        if($hand->canBePlacedAt($blockClicked, $clickVector, Facing::UP, true)){
            $blockReplace = $blockClicked;
            $hand->position($world, $replaceVector->x, $replaceVector->y, $replaceVector->z);
        }elseif(!$hand->canBePlacedAt($blockReplace, $clickVector, Facing::UP, false)){
            return false;
        }

        $ev = new BlockPlaceEvent($this->owningPlayer, $hand, $blockReplace, $blockClicked, $item);
        $ev->call();
        if($ev->isCancelled())
            return false;

        $tx = new BlockTransaction($world);
        if(!$hand->place($tx, $item, $blockReplace, $blockClicked, Facing::UP, $clickVector, $this->owningPlayer) || !$tx->apply())
            return false;

        foreach($tx->getBlocks() as [$x, $y, $z, $_]){
            $tile = $world->getTileAt($x, $y, $z);
            if($tile !== null){
                $tile->copyDataFromItem($item);
            }

            $world->getBlockAt($x, $y, $z)->onPostPlace();
        }

        $world->addSound($hand->getPos(), new BlockPlaceSound($hand));
        return true;
    }
}