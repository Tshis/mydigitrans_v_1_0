<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Dto\admin\agency\RouteData;
use App\Entity\Route as RouteEntity;
use App\Enum\Scope;
use App\Form\agency\RouteType;
use App\Service\RouteService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class LineController extends AbstractAdminController
{
    public function __construct(
        private readonly RouteService $routeService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/lines', name: 'admin_agency_line_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('route.view');
        $agency = $this->actor()->getAgency();

        return $this->render('admin/agency/line/index.html.twig', [
            'page' => 'line',
        ] + $this->routeService->listAgencyRoutes($agency));
    }

    #[Route('/admin/agency/line/add', name: 'admin_agency_line_add', methods: ['GET', 'POST'])]
    public function add(Request $request): Response
    {
        $this->denyAccessUnlessGranted('route.write');
        $actor = $this->actor();
        $agency = $actor->getAgency();

        $dto = new RouteData();
        $form = $this->createForm(RouteType::class, $dto, [
            'currency_choices' => $this->routeService->getAgencyCurrencyChoices($agency),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $route = $this->routeService->createRoute($actor, $dto);
                $this->addFlash('success', sprintf('La ligne « %s » (%s) a été créée avec %d arrêts.', $route->getName(), $route->getCode(), $route->getRouteStops()->count()));

                return $this->redirectToRoute('admin_agency_line_show', ['slug' => $route->getSlug()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/line/add.html.twig', [
            'page' => 'line',
            'form' => $form,
        ]);
    }

    #[Route('/admin/agency/line/{slug}/details', name: 'admin_agency_line_show', methods: ['GET'])]
    public function show(string $slug): Response
    {
        $this->denyAccessUnlessGranted('route.view');
        $route = $this->requireAgencyRoute($slug);

        return $this->render('admin/agency/line/show.html.twig', [
            'page' => 'line',
        ] + $this->routeService->getRouteProfile($route));
    }

    #[Route('/admin/agency/line/{slug}/toggle', name: 'admin_agency_line_toggle', methods: ['POST'])]
    public function toggle(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('route.write');
        $route = $this->requireAgencyRoute($slug);

        if (!$this->isCsrfTokenValid('route_toggle_' . $route->getSlug(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        $isNowAvailable = $this->routeService->toggleRouteAvailability($route);
        $this->addFlash(
            'success',
            $isNowAvailable ? 'La ligne est désormais opérationnelle.' : 'La ligne a été suspendue.'
        );

        return $this->redirectToRoute('admin_agency_line_show', ['slug' => $route->getSlug()]);
    }

    #[Route('/admin/agency/line/{slug}/stop/{id}/toggle', name: 'admin_agency_line_stop_toggle', methods: ['POST'])]
    public function toggleStop(string $slug, int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('route.write');
        $route = $this->requireAgencyRoute($slug);

        if (!$this->isCsrfTokenValid('route_stop_toggle_' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Jeton CSRF invalide.');
        }

        try {
            $isActive = $this->routeService->toggleStopAvailability($route, $id);
            $this->addFlash('success', $isActive ? 'Escale réactivée.' : 'Escale suspendue.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_agency_line_show', ['slug' => $route->getSlug()]);
    }

    private function requireAgencyRoute(string $slug): RouteEntity
    {
        $route = $this->routeService->findAgencyRouteBySlug($this->actor()->getAgency(), $slug);
        if (!$route instanceof RouteEntity) {
            throw $this->createNotFoundException('Ligne introuvable dans votre agence.');
        }

        return $route;
    }
}
