<?php

namespace Bethel\UserBundle\Entity;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use BeSimple\SsoAuthBundle\Security\Core\User\UserFactoryInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Repository\DefaultRepositoryFactory;

/**
 * UserRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class UserRepository extends EntityRepository implements UserProviderInterface, UserFactoryInterface
{
	private $bethelApiKey;

    public function __construct(EntityManager $em, ClassMetadata $class, $bethelApiKey = null) {
        parent::__construct($em, $class);
        $this->bethelApiKey = $bethelApiKey;
    }

    public function testFunc($username) {
        $q = $this
            ->createQueryBuilder('u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->getQuery();

            $user = $q->getSingleResult();
            return $user;
    }

    public function loadUserByUsername($username) {
        $q = $this
            ->createQueryBuilder('u')
            ->where('u.username = :username')
            ->setParameter('username', $username)
            ->getQuery();

        try {
            // The Query::getSingleResult() method throws an exception
            // if there is no record matching the criteria.
            $user = $q->getSingleResult();
        } catch (NoResultException $e) {
            $user = $this->createUser($username, array('ROLE_STUDENT'), array());
        }

        return $user;
    }

    public function refreshUser(UserInterface $user) {
        $class = get_class($user);
        if (!$this->supportsClass($class)) {
            throw new UnsupportedUserException(
                sprintf(
                    'Instances of "%s" are not supported.',
                    $class
                )
            );
        }

        return $this->find($user->getId());
    }

    public function supportsClass($class) {
        return $this->getEntityName() === $class
        || is_subclass_of($class, $this->getEntityName());
    }

    /**
     * Creates a new user for the given username
     *
     * @param string $username The username
     * @param array $roles Roles assigned to user
     * @param array $attributes Attributes provided by SSO server
     *
     * @return \Symfony\Component\Security\Core\User\UserInterface
     */
    public function createUser($username, array $roles, array $attributes) {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@bethel.edu');
        $em = $this->getEntityManager();

        foreach($roles as $role) {
            $roleObject = $em->getRepository('BethelUserBundle:Role')->findOneBy(array(
                'role' => $role
            ));
            $user->addRole($roleObject);
        }
        // Add to database 
        $em->persist($user);
        $em->flush();
        
        return $user;
    }

    /**
     * @param string $role
     * @return array
     */
    public function getUserByRole($role) {
        // This gets Users by role (ROLE_TUTOR, ROLE_STUDENT)
        // NOT name (Tutor, Student)
        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.roles','s','WITH','s.role = :role')
            ->setParameter('role', $role);
        return $qb->getQuery()->getResult();
    }

    /**
     * @param string $role
     * @param string $firstName
     * @param string $lastName
     * @return array
     */
    public function getStudentsWithUsernameFirstNameLastName($username, $firstName, $lastName) {
      $query = $this->createQueryBuilder('p')
        ->innerJoin('p.roles','s','WITH','s.role LIKE :role')
        ->where('p.username LIKE :username')
        ->andWhere('p.firstName LIKE :firstName')
        ->andWhere('p.lastName LIKE :lastName')
        ->setParameters(
            array(
              "username"=> '%'.$username.'%', 
              "firstName"=> '%'.$firstName.'%', 
              "lastName"=> '%'.$lastName.'%', 
              'role' => '%ROLE_STUDENT%'
              )
            )
        ->getQuery();

      // Store the results in an array
      return $query->getResult();
    }

    /**
     * @param string $role
     * @return array
     */
    public function getUserByName($role) {
        // This gets Users by role NAME (Tutor, Student)
        // NOT role (ROLE_TUTOR, ROLE_STUDENT)
        $qb = $this->createQueryBuilder('u')
            ->innerJoin('u.roles','s','WITH','s.name = :role')
            ->setParameter('role', $role);
        return $qb->getQuery()->getResult();
    }

    public function getUsernameForApiKey($apiKey) {
        // Look up the username based on the token in the database, via
        // an API call, or do something entirely different
        if($apiKey == $this->bethelApiKey) {
            $username = 'apiuser';
        } else {
            $username = null;
        }

        return $username;
    }
}
