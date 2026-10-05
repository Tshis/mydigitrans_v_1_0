<?php

namespace App\Controller\web\admin\platform;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PartnerController extends AbstractController
{

    /**
     * REGISTRE : INDEXATION DU RÉSEAU DES APPORTEURS D'AFFAIRES ET SPONSORS MLM
     */
    #[Route('/admin/platform/partners', name: 'admin_platform_partner_index', methods: ['GET'])]
    public function index(): Response
    {
        // Hydratation stricte selon tes champs d'auto-relation parent_id et token public
        $partnersList = [
            [
                'id' => 10,
                'name' => 'Dieudonné Ilunga',
                'email' => 'dieudonne@mydigitrans.com',
                'referral_token' => 'MOMBONGO85',
                'parent_name' => 'Daniel Lukonu', // Sponsor parent_id
                'children_count' => 3,
                'total_gains' => 142.50,
                'createdAt' => new \DateTime('2025-01-15'),
                'isActive' => true
            ],
            [
                'id' => 11,
                'name' => 'Blaise Mvumbi',
                'email' => 'blaise@mydigitrans.com',
                'referral_token' => 'REF-BLAISE9',
                'parent_name' => 'Dieudonné Ilunga', // Niveau 2 !
                'children_count' => 1,
                'total_gains' => 38.00,
                'createdAt' => new \DateTime('2025-03-22'),
                'isActive' => true
            ],
            [
                'id' => 12,
                'name' => 'Gabriel Nsimba',
                'email' => 'gabriel@mydigitrans.com',
                'referral_token' => 'GAB-TRANS-26',
                'parent_name' => null, // Racine
                'children_count' => 0,
                'total_gains' => 0.00,
                'createdAt' => new \DateTime('2026-06-05'),
                'isActive' => false
            ]
        ];

        return $this->render('admin/platform/partner/index.html.twig', [
            'page' => 'partner',
            'partners_list' => $partnersList
        ]);
    } //index

    // src/Controller/Admin/Platform/PlatformPartnerController.php

// ... (Conserve ta méthode index() existante)

    /**
     * ADD : INSCRIPTION D'UN NOUVEL APPORTEUR PAR L'ADMINISTRATION
     */
    #[Route('/admin/platform/partners/add', name: 'admin_platform_partner_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            // EN INTÉGRATION DOCTRINE FINALE :
            // $user = new User();
            // $user->setName($request->request->get('name'));
            // $user->setReferralToken(strtoupper($request->request->get('referral_token')));
            // if ($parentId = $request->request->get('parent_id')) { $user->setParent($userRepository->find($parentId)); }
            // $em->persist($user); $em->flush();

            $this->addFlash('success', 'Le partenaire a été greffé au réseau MLM MyDigitrans.');
            return $this->redirectToRoute('admin_platform_partner_index');
        }

        $sponsors = [['id' => 10, 'name' => 'Dieudonné Ilunga', 'referral_token' => 'MOMBONGO85']];

        return $this->render('admin/platform/partner/form.html.twig', [
            'page' => 'partner',
            'is_edit' => false,
            'available_sponsors' => $sponsors
        ]);
    }//add

    /**
     * EDIT : AJUSTEMENT DE L'IDENTITÉ OU DU PARRAIN DU COMPTE
     */
    #[Route('/admin/platform/partners/{id}/edit', name: 'admin_platform_partner_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $partner = ['id' => $id, 'name' => 'Blaise Mvumbi', 'email' => 'blaise@mydigitrans.com', 'referral_token' => 'REF-BLAISE9', 'parent_id' => 10];
        $sponsors = [['id' => 10, 'name' => 'Dieudonné Ilunga', 'referral_token' => 'MOMBONGO85']];

        if ($request->isMethod('POST')) {
            $this->addFlash('success', 'Les verrous de sécurité généalogiques du compte ont été mis à jour.');
            return $this->redirectToRoute('admin_platform_partner_index');
        }

        return $this->render('admin/platform/partner/form.html.twig', [
            'page' => 'partner',
            'is_edit' => true,
            'partner' => $partner,
            'available_sponsors' => $sponsors
        ]);
    }//edit

    /**
     * TOGGLE : COMMUTATEUR COMPTE ACTIF / VERROUILLÉ EN UN CLIC
     */
    #[Route('/admin/platform/partners/{id}/toggle', name: 'admin_platform_partner_toggle', methods: ['POST'])]
    public function toggleStatus(int $id): Response
    {
        // LOGIQUE D'INVERSION ORM COMPTE USER :
        // $user = $em->find(User::class, $id); $user->setIsActive(!$user->isIsActive()); $em->flush();

        $this->addFlash('success', 'L\'autorisation d\'accès et de perception de l\'apporteur a été commutée.');
        return $this->redirectToRoute('admin_platform_partner_index');
    } //toggle
}
