<?php

namespace App\Tests\Security;

use App\Entity\User;
use App\Tests\DbWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class RegistrationTest extends DbWebTestCase
{
    public function testRegisterCreatesUser(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/register');
        self::assertResponseIsSuccessful();

        // On récupère le form sans dépendre du texte exact du bouton
        $form = $crawler->filter('form')->form();

        $email = 'newuser@test.local';

        // Champs standard du maker registration
        $form['registration_form[email]'] = $email;
        $form['registration_form[plainPassword]'] = 'TestPass123!';

        // Si ton template contient "agreeTerms", sinon commente la ligne
        if (isset($form['registration_form[agreeTerms]'])) {
            $form['registration_form[agreeTerms]'] = 1;
        }

        $client->submit($form);

        // Le maker redirige après register
        self::assertResponseRedirects();

        // Vérifie en DB
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $created = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertNotNull($created);
        self::assertNotSame('', (string) $created->getPassword());

        // Et que l'accès dashboard marche (auto-login = yes)
        $client->followRedirect();
        $client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
    }
}