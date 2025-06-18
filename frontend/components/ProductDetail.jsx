import React, {useEffect, useState} from 'react';
import {useParams, useNavigate} from 'react-router-dom';
import callApi from '@api/callApi';
import Container from '@components/Wrapper/Container';
import ErrorMessage from '@components/ErrorMessage';

const ProductDetail = () => {
    const {product: productId} = useParams();
    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const navigate = useNavigate();

    useEffect(() => {
        const fetchProduct = async () => {
            try {
                const response = await callApi({
                    method: 'GET',
                    path: `/api/products/${productId}`,
                    authType: 'client'
                });

                setProduct(response.data);
            } catch (err) {
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchProduct();
    }, [productId]);

    if (loading) {
        return <div className="p-8 text-center text-gray-600">Loading product...</div>;
    }

    return (
        <Container>
            {error && <ErrorMessage message={error}/>}
            {!error && (
                <div className="min-h-screen bg-gray-50 py-10 px-4">
                    <button
                        onClick={() => navigate(-1)}
                        className="text-indigo-600 hover:underline mb-6 block"
                    >
                        &larr; Back to Products
                    </button>

                    <div className="max-w-5xl mx-auto bg-white shadow rounded-lg p-6 md:flex gap-10">

                        <div className="md:w-1/2">
                            <img
                                src={product.image || product.thumbnail || 'https://via.placeholder.com/600x400?text=Template'}
                                alt={product.name}
                                className="w-full h-auto rounded"
                            />
                        </div>

                        <div className="md:w-1/2 mt-6 md:mt-0">
                            <h1 className="text-3xl font-bold text-gray-800 mb-2">{product.name}</h1>
                            <p className="text-sm text-gray-500 mb-4">{product.category || 'Template'}</p>
                            <p className="text-2xl text-indigo-600 font-semibold mb-4">£{(product.price / 100).toFixed(2)}</p>

                            <div className="text-gray-700 mb-6 whitespace-pre-line">
                                {product.description || 'No description available for this product.'}
                            </div>

                            <button
                                className="bg-indigo-600 text-white px-6 py-3 rounded hover:bg-indigo-700 transition"
                                onClick={() => alert('Add to basket logic here')}
                            >
                                Add to Basket
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </Container>
    );
};

export default ProductDetail;