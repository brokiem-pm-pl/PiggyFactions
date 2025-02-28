<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyFactions\commands\subcommands\claims\claim;

use DaPigGuy\PiggyFactions\commands\subcommands\FactionSubCommand;
use DaPigGuy\PiggyFactions\event\claims\ChunkOverclaimEvent;
use DaPigGuy\PiggyFactions\event\claims\ClaimChunkEvent;
use DaPigGuy\PiggyFactions\factions\Faction;
use DaPigGuy\PiggyFactions\players\FactionsPlayer;
use pocketmine\player\Player;
use pocketmine\world\format\Chunk;

class ClaimSubCommand extends FactionSubCommand
{
    public function onNormalRun(Player $sender, ?Faction $faction, FactionsPlayer $member, string $aliasUsed, array $args): void
    {
        if (in_array($sender->getWorld()->getFolderName(), $this->plugin->getConfig()->getNested("factions.claims.blacklisted-worlds"))) {
            $member->sendMessage("commands.claim.blacklisted-world");
            return;
        }
        if (!$member->isInAdminMode()) {
            if (($total = count($this->plugin->getClaimsManager()->getFactionClaims($faction))) >= ($max = $this->plugin->getConfig()->getNested("factions.claims.max", -1)) && $max !== -1) {
                $member->sendMessage("commands.claim.max-claimed");
                return;
            }
            if ($total >= floor($faction->getPower() / $this->plugin->getConfig()->getNested("factions.claim.cost", 1))) {
                $member->sendMessage("commands.claim.no-power");
                return;
            }
        }
        $claim = $this->plugin->getClaimsManager()->getClaimByPosition($sender->getPosition());
        if ($claim !== null) {
            if ($claim->canBeOverClaimed() && $claim->getFaction() !== $faction) {
                for ($adjX = -1; $adjX <= 1; ++$adjX) {
                    for ($adjZ = -1; $adjZ <= 1; ++$adjZ) {
                        if ($adjX === 0 && $adjZ === 0) continue;
                        $adjacentClaim = $this->plugin->getClaimsManager()->getClaim($claim->getChunkX() + $adjX, $claim->getChunkZ() + $adjZ, $sender->getWorld()->getFolderName());
                        if ($adjacentClaim !== null && $adjacentClaim->getFaction() === $faction) {
                            $ev = new ChunkOverclaimEvent($faction, $member, $claim);
                            $ev->call();
                            if ($ev->isCancelled()) return;

                            $member->sendMessage("commands.claim.over-claimed");
                            $claim->setFaction($faction);
                            return;
                        }
                    }
                }
            }
            $member->sendMessage("commands.claim.already-claimed");
            return;
        }
        $chunkX = $sender->getPosition()->getFloorX() >> Chunk::COORD_BIT_SIZE;
        $chunkZ = $sender->getPosition()->getFloorZ() >> Chunk::COORD_BIT_SIZE;

        $ev = new ClaimChunkEvent($faction, $member, $chunkX, $chunkZ);
        $ev->call();
        if ($ev->isCancelled()) return;

        $this->plugin->getClaimsManager()->createClaim($faction, $sender->getWorld(), $chunkX, $chunkZ);
        $member->sendMessage("commands.claim.success");
    }

    protected function prepare(): void
    {
        $this->registerSubCommand(new ClaimAutoSubCommand($this->plugin, "auto", "Automatically claim chunks", ["a"]));
        $this->registerSubCommand(new ClaimCircleSubCommand($this->plugin, "circle", "Claim chunks in a circle radius", ["c"]));
        $this->registerSubCommand(new ClaimSquareSubCommand($this->plugin, "square", "Claim chunks in a square radius", ["s"]));
    }
}