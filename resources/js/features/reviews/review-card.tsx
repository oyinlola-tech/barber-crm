import { useForm } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type Review = {
    id: number;
    rating: number;
    body: string | null;
    source: string;
    reviewed_at: string;
    customer: { first_name: string; last_name: string | null };
    visit: { visited_at: string } | null;
};

export default function ReviewCard({
    review,
    canUpdate,
}: {
    review: Review;
    canUpdate: boolean;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        rating: String(review.rating),
        body: review.body ?? '',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({ ...data, rating: Number(data.rating) }));
        form.put(`/reviews/${review.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    }

    return (
        <div className="flex gap-4">
            <div className="grid size-10 shrink-0 place-items-center rounded-full bg-muted">
                <Star size={18} className="text-amber-500" />
            </div>
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="font-medium">
                        {review.customer.first_name}{' '}
                        {review.customer.last_name ?? ''}
                    </p>
                    <span className="text-sm text-muted-foreground">
                        {review.rating}/5 ·{' '}
                        {new Date(review.reviewed_at).toLocaleDateString()}
                    </span>
                </div>
                {editing ? (
                    <form className="mt-3 grid gap-3" onSubmit={submit}>
                        <label className="grid gap-1 text-sm">
                            Rating
                            <select
                                className="h-10 rounded-lg border bg-background px-3"
                                value={form.data.rating}
                                onChange={(event) =>
                                    form.setData('rating', event.target.value)
                                }
                            >
                                {[5, 4, 3, 2, 1].map((rating) => (
                                    <option key={rating} value={rating}>
                                        {rating} out of 5
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1 text-sm">
                            Customer feedback
                            <textarea
                                className="min-h-24 rounded-lg border bg-background px-3 py-2"
                                maxLength={5000}
                                value={form.data.body}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                            />
                            {form.errors.body && (
                                <span className="text-destructive">
                                    {form.errors.body}
                                </span>
                            )}
                        </label>
                        <div className="flex gap-2">
                            <Button type="submit" disabled={form.processing}>
                                Save feedback
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setEditing(false);
                                    form.reset();
                                    form.clearErrors();
                                }}
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                ) : (
                    <>
                        {review.body && (
                            <p className="mt-2 whitespace-pre-wrap text-sm">
                                {review.body}
                            </p>
                        )}
                        <p className="mt-2 text-xs text-muted-foreground">
                            Source: {review.source}
                            {review.visit
                                ? ` · Visit ${new Date(review.visit.visited_at).toLocaleDateString()}`
                                : ''}
                        </p>
                        {canUpdate && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="mt-2 h-8 px-2"
                                onClick={() => setEditing(true)}
                            >
                                Edit feedback
                            </Button>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
