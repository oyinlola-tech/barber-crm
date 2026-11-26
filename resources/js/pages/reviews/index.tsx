import { Head, useForm } from '@inertiajs/react';
import { MessageSquareText, Star } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type CustomerOption = {
    id: number;
    first_name: string;
    last_name: string | null;
};

type Review = {
    id: number;
    rating: number;
    body: string | null;
    source: string;
    reviewed_at: string;
    customer: { first_name: string; last_name: string | null };
    visit: { visited_at: string } | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function ReviewsIndex({
    reviews,
    customers,
}: {
    reviews: Paginated<Review>;
    customers: Paginated<CustomerOption>;
}) {
    const form = useForm({
        customer_id: '',
        visit_id: '',
        rating: '5',
        body: '',
        source: 'internal',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            customer_id: Number(data.customer_id),
            visit_id: data.visit_id ? Number(data.visit_id) : null,
            rating: Number(data.rating),
        }));
        form.post('/reviews', { onSuccess: () => form.reset('body') });
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Customer feedback" />
            <div className="mx-auto max-w-6xl">
                <header className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        RETENTION
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        Customer feedback
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Keep a record of the feedback customers share with the
                        barbershop.
                    </p>
                </header>

                <section className="mb-6 rounded-2xl border bg-card p-5 sm:p-6">
                    <div className="flex items-center gap-2">
                        <MessageSquareText size={18} />
                        <h2 className="font-semibold">Record feedback</h2>
                    </div>
                    <form
                        className="mt-5 grid gap-4 md:grid-cols-2"
                        onSubmit={submit}
                    >
                        <label className="grid gap-1.5 text-sm font-medium">
                            Customer
                            <select
                                className="h-10 rounded-lg border bg-background px-3 font-normal"
                                onChange={(event) =>
                                    form.setData(
                                        'customer_id',
                                        event.target.value,
                                    )
                                }
                                required
                                value={form.data.customer_id}
                            >
                                <option value="">Choose a customer</option>
                                {customers.data.map((customer) => (
                                    <option
                                        key={customer.id}
                                        value={customer.id}
                                    >
                                        {customer.first_name}{' '}
                                        {customer.last_name ?? ''}
                                    </option>
                                ))}
                            </select>
                            {form.errors.customer_id && (
                                <span className="text-xs font-normal text-destructive">
                                    {form.errors.customer_id}
                                </span>
                            )}
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium">
                            Rating
                            <select
                                className="h-10 rounded-lg border bg-background px-3 font-normal"
                                onChange={(event) =>
                                    form.setData('rating', event.target.value)
                                }
                                value={form.data.rating}
                            >
                                {[5, 4, 3, 2, 1].map((rating) => (
                                    <option key={rating} value={rating}>
                                        {rating} out of 5
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium md:col-span-2">
                            What the customer shared
                            <textarea
                                className="min-h-24 rounded-lg border bg-background px-3 py-2 font-normal"
                                maxLength={5000}
                                onChange={(event) =>
                                    form.setData('body', event.target.value)
                                }
                                value={form.data.body}
                            />
                            {form.errors.body && (
                                <span className="text-xs font-normal text-destructive">
                                    {form.errors.body}
                                </span>
                            )}
                        </label>
                        <div className="md:col-span-2">
                            <Button
                                disabled={
                                    form.processing ||
                                    customers.data.length === 0
                                }
                                type="submit"
                            >
                                Save feedback
                            </Button>
                        </div>
                    </form>
                    {customers.data.length === 0 && (
                        <p className="mt-3 text-sm text-muted-foreground">
                            Add a customer before recording feedback.
                        </p>
                    )}
                </section>

                <section className="overflow-hidden rounded-2xl border bg-card">
                    <div className="border-b px-5 py-4">
                        <h2 className="font-semibold">Recent feedback</h2>
                    </div>
                    {reviews.data.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No customer feedback has been recorded.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {reviews.data.map((review) => (
                                <li className="flex gap-4 p-5" key={review.id}>
                                    <div className="grid size-10 shrink-0 place-items-center rounded-full bg-muted">
                                        <Star
                                            size={18}
                                            className="text-amber-500"
                                        />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="font-medium">
                                                {review.customer.first_name}{' '}
                                                {review.customer.last_name ??
                                                    ''}
                                            </p>
                                            <span className="text-sm text-muted-foreground">
                                                {review.rating}/5 ·{' '}
                                                {new Date(
                                                    review.reviewed_at,
                                                ).toLocaleDateString()}
                                            </span>
                                        </div>
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
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="flex items-center justify-between border-t px-5 py-3 text-sm">
                        <span className="text-muted-foreground">
                            Page {reviews.current_page} of {reviews.last_page}
                        </span>
                        <div className="flex gap-2">
                            {reviews.prev_page_url && (
                                <a
                                    className="rounded-md border px-3 py-1.5"
                                    href={reviews.prev_page_url}
                                >
                                    Previous
                                </a>
                            )}
                            {reviews.next_page_url && (
                                <a
                                    className="rounded-md border px-3 py-1.5"
                                    href={reviews.next_page_url}
                                >
                                    Next
                                </a>
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </main>
    );
}
