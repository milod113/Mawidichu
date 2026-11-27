export default function DataTable({ columns, rows, empty, rowKey = (r) => r.id }) {
    if (rows.length === 0) {
        return <p className="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">{empty}</p>;
    }

    return (
        <div className="overflow-x-auto rounded-lg ring-1 ring-gray-200 dark:ring-gray-800">
            <table className="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead className="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        {columns.map((c) => (
                            <th key={c.key} className="px-3 py-2.5 text-start text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {c.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-950">
                    {rows.map((row) => (
                        <tr key={rowKey(row)}>
                            {columns.map((c) => (
                                <td key={c.key} className="px-3 py-2.5 align-middle">
                                    {c.render ? c.render(row) : row[c.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
