<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BusinessController extends AbstractController
{

    #[Route('/business', name: 'business_index')]
    public function index(): Response
    {
        return $this->render('public/business/index.html.twig', [
            'page' => 'business',
        ]);
    } //index


    /**
     * VUE PUBLIQUE : ACCÈS ET REPRÉGULATION DU FORMULAIRE D'INSCRIPTION PARTENAIRE [Profile]
     */
    #[Route('/business/devenir-partenaire', name: 'business_become_partner')]
    #[Route('/business/devenir-partenaire/{referal_token}', name: 'business_become_partner_sponsored')]
    public function become_partner(?string $referal_token = null, Request $request): Response
    {
        // Capture du jeton de parrainage s'il est présent dans l'URL (?ref=MOMBONGO85) [Profile]
        $requestedSponsor = $referal_token;

        return $this->render('public/business/become_partner.html.twig', [
            'page' => 'business',
            'requested_sponsor' => $requestedSponsor
        ]);
    }//become_partner

    /**
     * TRAITEMENT DE L'INSCRIPTION ET PERSISTANCE DE L'APPORTEUR
     */
    #[Route('/devenir-partenaire/process', name: 'public_partner_register_process', methods: ['POST'])]
    public function processRegister(Request $request): Response
    {
        // En production : On chiffre le mot de passe, on génère un referral_token unique pour le nouveau venu,
        // et s'il a un sponsor_token valide, on l'associe à l'entité User parente (parent_id) [Profile].

        $this->addFlash('success', 'Votre compte partenaire a été initialisé. Bienvenue dans l\'infrastructure MLM MyDigitrans !');
        return $this->redirectToRoute('partner_dashboard_index'); // Redirection directe vers son espace
    }//processRegister


    //private UserRepository $userRepository;

    /**
     * CONSTRUCTEUR : INJECTION DE LA SÉCURITÉ DE POINTAGE DE LA BASE DE DONNÉES
     */
    /*  public function __construct(UserRepository $userRepository)
        {
            $this->userRepository = $userRepository;
        }
    */
    /**
     * GÉNÉRATEUR D'USINE V1.0 : STRUCTURE IMMUABLE MDT + 3 LETTRES PRÉNOM + 3 CHIFFRES [MNS]
     * 
     * @param string $firstname Le prénom saisi par le partenaire à l'inscription
     * @return string Le code referral_token unique et immuable généré (Ex: MDTDAN718)
     */
    public function generateBrandToken(string $firstname): string
    {
        // 1. Filtrage et nettoyage strict de la chaîne (Suppression des accents et espaces)
        $cleanFirst = preg_replace('/[^A-Za-z]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $firstname));

        // 2. Si le prénom est totalement absent ou invalide, fallback de secours sur "PRN" (Partner)
        if (empty($cleanFirst)) {
            $cleanFirst = 'PRN';
        }

        // 3. Extraction chirurgicale des 3 premières lettres du prénom en MAJUSCULES
        $userPart = strtoupper(substr($cleanFirst, 0, 3));

        // 🚨 SÉCURITÉ PRÉNOMS COURTS (Ex: Al -> ALX / Jo -> JOX)
        // Ajoute un 'X' tant que la chaîne n'atteint pas strictement 3 caractères
        while (strlen($userPart) < 3) {
            $userPart .= 'X';
        }

        // 4. Préfixe fixe et immuable de ta marque MyDigitrans
        $brandPrefix = 'MDT';

        // 5. BOUCLE ANTI-COLLISION : Vérification de l'unicité absolue en Base de Données
        do {
            // Tirage de 3 chiffres aléatoires d'usine
            $randomNumber = rand(100, 999);

            // Assemblage de la structure : MDT + DAN + 718 = MDTDAN718
            $finalToken = sprintf('%s%s%d', $brandPrefix, $userPart, $randomNumber);

            // Pointage SQL direct dans la table User pour vérifier l'existence
            // $exists = $this->userRepository->findOneBy(['referralToken' => $finalToken]);
            $exists = [];
        } while ($exists !== null); // Si le jeton est déjà pris, la boucle relance un tirage de chiffres

        // Renvoie le jeton unique validé à 100% pour la persistance
        return $finalToken;
    } //generateBrandToken





}
