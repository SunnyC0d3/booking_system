import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import callApi from '@api/callApi';
import Container from '@components/Wrapper/Container';

const Products = () => {
    const [products, setProducts] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate();

    useEffect(() => {
        const fetchProducts = async () => {
            try {
                const response = await callApi({
                    method: 'GET',
                    path: '/api/products',
                    authType: 'client'
                });

                if (response.status !== 200) {
                    throw new Error('Failed to fetch products');
                }
                console.log(response.data);
                setProducts(response.data);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchProducts();
    }, []);

    if (loading) {
        return <div className="p-8 text-center text-gray-600">Loading products...</div>;
    }

    if (error) {
        return <div className="p-8 text-center text-red-500">Error: {error}</div>;
    }

    return (
        <Container>
            <div className="min-h-screen bg-gray-50 px-4 py-10">
                <h1 className="text-3xl font-bold text-center text-indigo-700 mb-10">Browse Digital Templates</h1>
                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 max-w-7xl mx-auto">
                    {products.map((product) => (
                        <div
                            key={product.id}
                            className="bg-white p-4 rounded shadow hover:shadow-md cursor-pointer transition"
                            onClick={() => navigate(`/products/${product.id}`)}
                        >
                            <img
                                src={product.thumbnail || 'https://via.placeholder.com/300x200?text=Template'}
                                alt={product.title}
                                className="w-full h-40 object-cover rounded mb-4"
                            />
                            <h2 className="text-lg font-semibold text-gray-800">{product.name}</h2>
                            <p className="text-sm text-gray-500 mb-2">{product.category || 'Template'}</p>
                            <p className="text-indigo-600 font-bold">${product.price}</p>
                        </div>
                    ))}
                </div>
            </div>
        </Container>
    );
};

export default Products;
