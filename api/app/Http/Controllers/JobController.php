<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\JobRequest;
use App\Http\Requests\ReorderJobsRequest;
use App\Http\Resources\JobResource;
use App\Models\Database\v1\Job;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Jobs (Lavorazioni) with their suggested materials, api.md "Catalogo".
 */
final class JobController extends Controller
{
    /**
     * GET /jobs?active=1, in display order.
     *
     * @param Request $request
     *
     * @return AnonymousResourceCollection
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $jobs = Job::query()
                   ->with('jobMaterials.material')
                   ->when($request->has('active'), static fn ($query) => $query->where('active', $request->boolean('active')))
                   ->orderBy('sort_order')
                   ->orderBy('name')
                   ->paginate($this->perPage($request));

        return JobResource::collection($jobs);
    }

    /**
     * @param JobRequest $request
     *
     * @return JsonResponse
     */
    public function store(JobRequest $request): JsonResponse
    {
        $job = DB::transaction(static function () use ($request): Job {
            $job = Job::query()->create([
                ...$request->jobAttributes(),
                'sort_order' => (int) Job::query()->max('sort_order') + 1,
            ]);

            self::replaceMaterials($job, $request->materials() ?? []);

            return $job;
        });

        // refresh: database defaults (active, show_on_quote_pdf) are not on the new model
        return new JobResource($job->refresh()->load('jobMaterials.material'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param Job $job
     *
     * @return JobResource
     */
    public function show(Job $job): JobResource
    {
        return new JobResource($job->load('jobMaterials.material'));
    }

    /**
     * `materials[]`, when present, replaces the whole list.
     *
     * @param JobRequest $request
     * @param Job        $job
     *
     * @return JobResource
     */
    public function update(JobRequest $request, Job $job): JobResource
    {
        DB::transaction(static function () use ($request, $job): void {
            $job->update($request->jobAttributes());

            $materials = $request->materials();

            if (null !== $materials) {
                self::replaceMaterials($job, $materials);
            }
        });

        return new JobResource($job->load('jobMaterials.material'));
    }

    /**
     * Used in quotes: deactivated (200). Never used: deleted (204).
     *
     * @param Job $job
     *
     * @return JobResource|Response
     */
    public function destroy(Job $job): JobResource|Response
    {
        if ($job->quoteLines()->exists()) {
            $job->update(['active' => false]);

            return new JobResource($job->load('jobMaterials.material'));
        }

        $job->delete();

        return response()->noContent();
    }

    /**
     * @param ReorderJobsRequest $request
     *
     * @return Response
     */
    public function reorder(ReorderJobsRequest $request): Response
    {
        /** @var array<int, string> $ids */
        $ids = $request->validated('ids');

        DB::transaction(static function () use ($ids): void {
            foreach (array_values($ids) as $position => $id) {
                Job::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return response()->noContent();
    }

    /**
     * @param Job                              $job
     * @param array<int, array<string, mixed>> $materials suggested materials in display order
     *
     * @return void
     */
    private static function replaceMaterials(Job $job, array $materials): void
    {
        $job->jobMaterials()->delete();

        foreach ($materials as $position => $material) {
            $job->jobMaterials()->create([
                ...$material,
                'tenant_id' => $job->tenant_id,
                'sort_order' => $position,
            ]);
        }
    }
}
