<?php

class ParcelAuth {
    private $config;

    public function __construct($config) {
        $this->config = $config['parcelperform'];
    }

    public function getAccessToken() {
        $auth = base64_encode($this->config['client_id'] . ':' . $this->config['client_secret']);
        $headers = [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $response = httpPost($this->config['token_url'], $headers, [
            'grant_type' => 'client_credentials'
        ], true);

        if ($response['code'] !== 200) return null;

        $data = json_decode($response['body'], true);
        //return $data['access_token'] ?? null;

        return "eyJhbGciOiJSUzI1NiIsImtpZCI6ImM0bEd3OUR1MGx5bTdVTzl6OXZrQ1Rma1hLeW0tSy1NUHVBLWVsandLOFUifQ.eyJhdWQiOiI3enAzMzZLOWdtVHJBUGRybXF3RkxnWWF1TWtxaWhwdWRhTURwSWZpIiwiZXhwIjoxNzQ0Mjc3OTI5LCJpYXQiOjE3NDQyNzQzMjksImlzcyI6ImNvZ25pdG8tbWlncmF0ZSIsInNjb3BlIjoiUE9TVDphdXRoL29hdXRoL3Rva2VuLyBQT1NUOnBhcmNlbC92Mi8gR0VUOnBhcmNlbC92Mi8qLyBHRVQ6L2F1dGgvb2F1dGgvdGVzdC10b2tlbi8gUE9TVDovdjUvc2hpcG1lbnQvIFBPU1Q6L3Y1L2V2ZW50cy9jcmVhdGUvIEdFVDovdjUvc2hpcG1lbnQvbGlzdC8gR0VUOi92NS9zaGlwbWVudC9kZXRhaWxzLyBQT1NUOnY1L3NoaXBtZW50L3VwZGF0ZS8gUE9TVDp2NS9zaGlwbWVudC90cmlnZ2VyLWFkaG9jLXVwZGF0ZS8gUE9TVDphdXRoL29hdXRoL3Rlc3Qtc2VudHJ5LyBQT1NUOnY1L2Jvb2tpbmcvIFBPU1Q6djUvcmV0dXJuLyBQT1NUOnY1L3JldHVybi91cGRhdGUgR0VUOnY1L25vdGlmaWNhdGlvbi9mYWlsZWQtd2ViaG9vay1jb3VudCBQT1NUOnY1L25vdGlmaWNhdGlvbi9yZXNlbmQtZmFpbGVkLXdlYmhvb2tzIFBPU1Q6djUtMi0wL3NoaXBtZW50L3VwZGF0ZS8gR0VUOnY1LTItMC9zaGlwbWVudC9kZXRhaWxzLyBHRVQ6djUtMi0xL3NoaXBtZW50L2RldGFpbHMvIFBPU1Q6L3YxL2VkZC9jaGVja291dC8gR0VUOnY1L3NoaXBtZW50cy9kb2N1bWVudHMvIEdFVDp2NS9zaGlwbWVudHMvZG9jdW1lbnRzL2xhYmVscy8gR0VUOnY1L3NoaXBtZW50cy9kb2N1bWVudHMvKi8gR0VUOnY1LTItMy9zaGlwbWVudC9kZXRhaWxzLyBHRVQ6djUtMi00L3NoaXBtZW50L2RldGFpbHMvIFBPU1Q6djUtMi0wL3NoaXBtZW50L3RyYWNrLyBHRVQ6djUtMy0wL3NoaXBtZW50L2RldGFpbHMvIEdFVDp2NS9jYXJyaWVyLWNvbmZpZ3MvIiwic3ViIjoiN3pwMzM2SzlnbVRyQVBkcm1xd0ZMZ1lhdU1rcWlocHVkYU1EcElmaSJ9.TI677NnGDxZDcE6ev1jxbWTzfCf-RcoTcOeJZ7BoAp-L5NG5WKI73hY_tllbM_5h3Q6Ug65F7LJrdpyqcY8jioQqBw_1LRNUvtlLrRDiCaa63UbKkaNKY6KsvLI1KfV2cmV6ngujJsRiy1NkTaVLDGWLRrywAy8kdhLtSbj3BcdC8D6L3QzjaG3bwEAdpZz3D7hfFGf5pl0_GTVszSpv2xIsv0vTzzvfPN6ccq-hXpiDoTBshPTkDS-j5ndulEjxjQznkBMMkqmCLk2A9vx_thaV3qvz6u2rnHUQC_eVxXfIxF9CgBudWWNrxpOWMENmlgoBINMaJkyCC4vPhUMbHQ";
    }
}
