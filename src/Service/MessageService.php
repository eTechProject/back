<?php
// src/Service/MessageService.php
namespace App\Service;

use App\Entity\Messages;
use App\Repository\ServiceOrdersRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MessageService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ServiceOrdersRepository $ordersRepo,
        private UserRepository $userRepo,
        private HubInterface $mercureHub,
    ) {}

    /**
     * Crée un message, le persiste et le publie via Mercure.
     *
     * @throws \Exception si validation échoue
     */
    public function createMessage(array $data): Messages
    {
        $orderId = $data['order_id'] ?? null;
        $senderId = $data['sender_id'] ?? null;
        $receiverId = $data['receiver_id'] ?? null;
        $content = trim($data['content'] ?? '');

        if (!$orderId || !$senderId || !$receiverId || empty($content)) {
            throw new \InvalidArgumentException('Paramètres manquants ou invalides');
        }

        $order = $this->ordersRepo->find($orderId);
        $sender = $this->userRepo->find($senderId);
        $receiver = $this->userRepo->find($receiverId);

        if (!$order || !$sender || !$receiver) {
            throw new \InvalidArgumentException('Commande ou utilisateurs non trouvés');
        }

        // Validation métier : sender et receiver doivent être liés à la commande
        if (
            !$order->getUsers()->contains($sender) ||
            !$order->getUsers()->contains($receiver)
        ) {
            throw new \InvalidArgumentException('Sender ou receiver non liés à cette commande');
        }

        $message = new Messages();
        $message->setOrder($order);
        $message->setSender($sender);
        $message->setReceiver($receiver);
        $message->setContent($content);
        $message->setSentAt(new \DateTimeImmutable());

        $this->em->persist($message);
        $this->em->flush();

        // Publication Mercure
        $payload = [
            'id' => $message->getId(),
            'order_id' => $orderId,
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'content' => $content,
            'sent_at' => $message->getSentAt()->format('Y-m-d H:i:s'),
        ];

        $updateAgent = new Update('/agents/' . $senderId, json_encode($payload));
        $updateClient = new Update('/clients/' . $receiverId, json_encode($payload));

        $this->mercureHub->publish($updateAgent);
        $this->mercureHub->publish($updateClient);

        return $message;
    }

    public function getMessagesForOrder(int $orderId): array
    {
        $order = $this->ordersRepo->find($orderId);

        if (!$order) {
            throw new \InvalidArgumentException('Commande non trouvée');
        }

        $messages = $order->getMessages();

        $result = [];
        foreach ($messages as $message) {
            $result[] = [
                'id' => $message->getId(),
                'sender_id' => $message->getSender()->getId(),
                'receiver_id' => $message->getReceiver()->getId(),
                'content' => $message->getContent(),
                'sent_at' => $message->getSentAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $result;
    }
}
