<?php

namespace JulienLinard\Doctrine\Exceptions;

/**
 * Exception levée lorsqu'une entité n'est pas trouvée
 */
class EntityNotFoundException extends DoctrineException
{
    /**
     * Constructeur
     *
     * @param string $entityClass Classe de l'entité
     * @param int|string $id Identifiant recherché
     */
    public function __construct(string $entityClass, int|string $id)
    {
        parent::__construct(
            "L'entité {$entityClass} avec l'ID {$id} n'a pas été trouvée."
        );
    }
}

