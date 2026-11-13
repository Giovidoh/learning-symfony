<?php

namespace App\Security\Voter;

use App\Entity\Recipe;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class RecipeVoter extends Voter
{
    public const LIST = 'RECIPE_LIST';
    public const LIST_ALL = 'RECIPE_ALL'; // non supporté ici : seul l'AdminVoter l'accorde
    public const CREATE = 'RECIPE_CREATE';
    public const EDIT = 'RECIPE_EDIT';
    public const VIEW = 'RECIPE_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CREATE, self::LIST])
            || (in_array($attribute, [self::EDIT, self::VIEW]) && $subject instanceof Recipe);
    }

    /**
     * @param Recipe|null $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason('The user must be logged in to access this resource.');

            return false;
        }

        return match ($attribute) {
            self::LIST, self::CREATE, self::VIEW => true,
            // Comparaison d'objets : false si la recette n'a pas d'auteur (au lieu d'une erreur 500)
            self::EDIT => $subject->getUser() === $user,
            default => false,
        };
    }
}
