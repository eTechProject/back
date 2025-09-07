<?php

namespace App\Service;

use App\Entity\Tasks;
use App\Entity\AgentLocationsArchive;
use App\Entity\Messages;
use App\Repository\AgentLocationsArchiveRepository;
use App\Repository\MessagesRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;

class AIReportService
{
    private const GOOGLE_AI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';
    
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AgentLocationsArchiveRepository $archiveRepository,
        private readonly MessagesRepository $messagesRepository,
        private readonly LoggerInterface $logger,
        private readonly string $googleApiKey
    ) {}

    /**
     * Generate AI report for a completed task
     */
    public function generateTaskReport(Tasks $task): array
    {
        try {
            // Collect all data needed for the report
            $reportData = $this->collectTaskData($task);
            
            // Build the AI prompt with all the data
            $prompt = $this->buildReportPrompt($reportData);
            
            // Send request to Google Gemini AI
            $aiResponse = $this->callGoogleAI($prompt);
            
            return [
                'status' => 'success',
                'message' => 'Rapport généré avec succès',
                'data' => [
                    'report' => $aiResponse,
                    'task_id' => $task->getId(),
                    'generated_at' => (new \DateTimeImmutable())->format('c')
                ]
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la génération du rapport IA', [
                'task_id' => $task->getId(),
                'error' => $e->getMessage()
            ]);
            
            throw new \RuntimeException('Erreur lors de la génération du rapport: ' . $e->getMessage());
        }
    }

    /**
     * Collect all data needed for the task report
     */
    private function collectTaskData(Tasks $task): array
    {
        $data = [
            'task' => [
                'id' => $task->getId(),
                'description' => $task->getDescription(),
                'type' => $task->getType()->value,
                'assign_position' => $task->getAssignPosition(),
                'start_date' => $task->getStartDate()->format('d/m/Y H:i:s'),
                'end_date' => $task->getEndDate()?->format('d/m/Y H:i:s') ?? 'Non terminée',
                'status' => $task->getStatus()->value
            ],
            'client' => [
                'name' => $task->getOrder()->getClient()->getName(),
                'email' => $task->getOrder()->getClient()->getEmail(),
                'phone' => $task->getOrder()->getClient()->getPhone()
            ],
            'agent_user' => [
                'name' => $task->getAgent()->getUser()->getName(),
                'phone' => $task->getAgent()->getUser()->getPhone()
            ],
            'agent' => [
                'sexe' => $task->getAgent()->getSexe()->value,
                'address' => $task->getAgent()->getAddress(),
                'profile_picture_url' => $task->getAgent()->getProfilePictureUrl()
            ]
        ];

        // Get archive data if available
        $archive = $this->archiveRepository->findByTaskId($task->getId());
        if ($archive) {
            $data['agent_locations_archive'] = [
                'path_length' => $archive->getPathLength(),
                'avg_speed' => round($archive->getAvgSpeed() * 3.6, 2), // Convert m/s to km/h
                'start_time' => $archive->getStartTime()->format('d/m/Y H:i:s'),
                'end_time' => $archive->getEndTime()->format('d/m/Y H:i:s'),
                'point_count' => $archive->getPointCount()
            ];
        } else {
            $data['agent_locations_archive'] = [
                'path_length' => 0,
                'avg_speed' => 0,
                'start_time' => 'Non disponible',
                'end_time' => 'Non disponible',
                'point_count' => 0
            ];
        }

        // Get messages related to this task's order within the task date range
        $taskStartDate = $task->getStartDate();
        $taskEndDate = $task->getEndDate();
        
        $messages = $this->messagesRepository->findMessagesByOrderAndDateRange(
            $task->getOrder()->getId(),
            $taskStartDate,
            $taskEndDate
        );

        $messagesContent = '';
        foreach ($messages as $message) {
            $senderRole = $message->getSender()->getRole()->value;
            $senderName = $message->getSender()->getName();
            $sentAt = $message->getSentAt()->format('d/m/Y H:i:s');
            $content = $message->getContent();
            
            $messagesContent .= "[$sentAt] $senderName ($senderRole): $content\n";
        }
        
        $data['messages_content'] = $messagesContent ?: 'Aucun message échangé pendant la période de la mission';

        return $data;
    }

    /**
     * Build the AI prompt with task data
     */
    private function buildReportPrompt(array $data): string
    {
        return "Tu es un assistant chargé de rédiger un rapport officiel de mission de sécurité en français.
        Génère un rapport clair, professionnel et synthétique basé sur les informations suivantes :

        ### Informations sur la mission
        - ID de la tâche : {$data['task']['id']}
        - Description : {$data['task']['description']}
        - Type de mission : {$data['task']['type']}
        - Position assignée (coordonnées) : {$data['task']['assign_position']}
        - Date de début : {$data['task']['start_date']}
        - Date de fin : {$data['task']['end_date']}
        - Statut de la mission : {$data['task']['status']}

        ### Informations sur le client
        - Nom : {$data['client']['name']}
        - Email : {$data['client']['email']}
        - Téléphone : {$data['client']['phone']}

        ### Informations sur l'agent
        - Nom : {$data['agent_user']['name']}
        - Sexe : {$data['agent']['sexe']}
        - Adresse : {$data['agent']['address']}
        - Téléphone : {$data['agent_user']['phone']}
        - Photo de profil : {$data['agent']['profile_picture_url']}

        ### Déplacements de l'agent (agent_locations_archive)
        - Distance totale parcourue : {$data['agent_locations_archive']['path_length']} mètres
        - Vitesse moyenne : {$data['agent_locations_archive']['avg_speed']} km/h
        - Durée de présence : de {$data['agent_locations_archive']['start_time']} à {$data['agent_locations_archive']['end_time']}
        - Nombre de points GPS enregistrés : {$data['agent_locations_archive']['point_count']}

        ### Communications échangées (messages)
        Voici l'ensemble des messages échangés entre le client et l'agent concernant la mission :
        {$data['messages_content']}

        Analyse et résume ces messages en un paragraphe en français, en mettant en évidence :
        - Les instructions importantes du client,
        - Les réponses et confirmations de l'agent,
        - Les éventuelles difficultés rencontrées ou incidents signalés.

        ### Tâche finale
        1. Fournis un **rapport structuré en français** avec les sections suivantes :
        - Contexte de la mission
        - Informations sur l'agent
        - Informations sur le client
        - Déroulement de la mission (déplacements et activité de l'agent)
        - Synthèse des communications (analyse des messages)
        - Conclusion et recommandations
        2. Le style doit être professionnel, clair et concis.
        3. Mets les dates et chiffres au format français.";
    }

    /**
     * Call Google Gemini AI API
     */
    private function callGoogleAI(string $prompt): string
    {
        try {
            $response = $this->httpClient->request('POST', self::GOOGLE_AI_API_URL, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-goog-api-key' => $this->googleApiKey
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt
                                ]
                            ]
                        ]
                    ]
                ]
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                throw new \RuntimeException("Erreur API Google AI: Status $statusCode");
            }

            $responseData = $response->toArray();
            
            if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
                throw new \RuntimeException('Format de réponse API Google AI invalide');
            }

            return $responseData['candidates'][0]['content']['parts'][0]['text'];

        } catch (TransportExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {
            $this->logger->error('Erreur lors de l\'appel à l\'API Google AI', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Erreur de communication avec l\'API Google AI: ' . $e->getMessage());
        }
    }
}
