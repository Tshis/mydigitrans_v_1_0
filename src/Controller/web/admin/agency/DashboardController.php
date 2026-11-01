<?php

namespace App\Controller\web\admin\agency;

use App\Entity\User;
use App\Service\AgencyDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{

    #[Route('/admin/agency/dashboard', name: 'admin_agency_dashboard')]
    public function index(Request $request, AgencyDashboardService $dashboard): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Utilisateur connecté (entité 3. User) : l'agence et la succursale déterminent ce qu'il voit.
        // Siège (succursale vide ou « SIEGE ») : toute l'agence, avec le choix d'une succursale à observer.
        // Autre succursale : uniquement ses propres flux.
        $user = $this->getUser();

        if (!$user instanceof User || null === $user->getAgency()) {
            throw $this->createAccessDeniedException('Aucune agence n\'est associée à ce compte.');
        }

        $view = $dashboard->build(
            $user,
            (string) $request->query->get('branch_code', ''),
            new \DateTimeImmutable()
        );

        return $this->render('admin/agency/dashboard/index.html.twig', ['page' => 'dashboard'] + $view);
    } //index
}
