<?php
namespace App\Tests\Functional\Controller\Client;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\Client;
use App\Entity\Payment;
use App\Entity\PaymentHistory;
use App\Enum\PaymentStatus;
use App\Enum\PaymentHistoryStatus;
use App\Entity\Pack;
use Doctrine\ORM\EntityManagerInterface;

class GetClientPaymentsControllerTest extends WebTestCase
{
    public function testGetClientPaymentsWithHistoryEndpoint()
    {
        $client = static::createClient();
        $em = $client->getContainer()->get(EntityManagerInterface::class);

        // Crée un pack fictif (champs obligatoires)
        $pack = new Pack();
        $pack->setNbAgents(1);
        $pack->setPrix('10.0');
        $pack->setDateCreation(new \DateTimeImmutable());
        $pack->setDescription('Pack Test');
        $em->persist($pack);
        $em->flush();

        // Crée un client fictif
        $user = new Client();
        $user->setEmail('test@example.com');
        $em->persist($user);
        $em->flush();

        // Crée un paiement fictif
        $payment = new Payment();
        $payment->setClient($user);
        $payment->setPack($pack);
        $payment->setStatus(PaymentStatus::ACTIF);
        $em->persist($payment);
        $em->flush();

        // Crée un historique fictif
        $history = new PaymentHistory();
        $history->setPayment($payment);
        $history->setAmount(10.0);
        $history->setStatus(PaymentHistoryStatus::SUCCESS);
        $history->setProvider('test');
        $em->persist($history);
        $em->flush();

        // Appelle l'endpoint
        $client->request('GET', '/api/client/' . $user->getId() . '/payment');
        $response = $client->getResponse();

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('history', $data);
        $this->assertArrayHasKey('active_payments', $data);
        $this->assertArrayHasKey('expired_payments', $data);
        $this->assertArrayHasKey('other_payments', $data);
    }
}
